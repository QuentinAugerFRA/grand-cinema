<?php

namespace App\Service;

use PDOException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class Reservations
{
    private const MAXIMUM_PLACES = 10;
    private const DUREE_BLOCAGE = '-10 minutes';
    private const EXPEDITEUR = 'billetterie@grandcinema.fr';

    public function __construct(
        private BaseDeDonnees $base,
        private Paiement $paiement,
        private MailerInterface $messagerie
    )
    {
    }

    public function plan(int $seanceId): array
    {
        $this->libererReservationsExpirees();

        return $this->base->lire(
            'SELECT place.id, place.rangee, place.numero, reservation_place.place_id IS NOT NULL AS occupee
             FROM seance
             JOIN place ON place.salle_id = seance.salle_id
             LEFT JOIN reservation_place ON reservation_place.seance_id = seance.id AND reservation_place.place_id = place.id
             WHERE seance.id = ?
             ORDER BY place.rangee, place.numero',
            [$seanceId]
        );
    }

    // Bloque les places pendant 10 minutes, le temps du paiement.
    public function reserver(int $utilisateurId, int $seanceId, array $placeIds): array
    {
        $placeIds = array_values(array_unique(array_map('intval', $placeIds)));
        $nombrePlaces = count($placeIds);
        if ($nombrePlaces < 1 || $nombrePlaces > self::MAXIMUM_PLACES)
        {
            throw new BadRequestHttpException('Une réservation contient de 1 à 10 places.');
        }
        $seance = $this->base->lire('SELECT tarif FROM seance WHERE id = ? AND debut > ?', [$seanceId, date('Y-m-d H:i:s')])[0]
            ?? throw new NotFoundHttpException('Séance introuvable ou déjà commencée.');
        $montant = $nombrePlaces * $seance['tarif'];
        $this->libererReservationsExpirees();

        try
        {
            return $this->base->transaction(function () use ($utilisateurId, $seanceId, $placeIds, $nombrePlaces, $montant)
            {
                $this->base->ecrire(
                    'INSERT INTO reservation (utilisateur_id, seance_id, montant, cree_le) VALUES (?, ?, ?, ?)',
                    [$utilisateurId, $seanceId, $montant, date('Y-m-d H:i:s')]
                );
                $reservationId = $this->base->dernierIdentifiant();
                $marqueurs = implode(', ', array_fill(0, $nombrePlaces, '?'));
                $placesBloquees = $this->base->ecrire(
                    "INSERT INTO reservation_place (reservation_id, seance_id, place_id)
                     SELECT ?, seance.id, place.id
                     FROM seance
                     JOIN place ON place.salle_id = seance.salle_id
                     WHERE seance.id = ? AND place.id IN ($marqueurs)",
                    [$reservationId, $seanceId, ...$placeIds]
                );
                if ($placesBloquees !== $nombrePlaces)
                {
                    throw new BadRequestHttpException('Une des places n\'existe pas dans cette salle.');
                }

                return ['id' => $reservationId, 'montant' => $montant];
            });
        }
        catch (PDOException)
        {
            throw new ConflictHttpException('Une des places vient d\'être réservée par un autre spectateur.');
        }
    }

    public function payer(int $reservationId, int $utilisateurId, string $numeroCarte): array
    {
        $this->libererReservationsExpirees();
        $reservation = $this->base->lire(
            "SELECT montant FROM reservation WHERE id = ? AND utilisateur_id = ? AND statut = 'en_attente'",
            [$reservationId, $utilisateurId]
        )[0] ?? throw new NotFoundHttpException('Réservation introuvable ou expirée.');

        if (!$this->paiement->debiter($numeroCarte, $reservation['montant']))
        {
            $this->liberer($reservationId);
            throw new HttpException(402, 'Paiement refusé : vos places ont été libérées.');
        }
        $code = bin2hex(random_bytes(16));
        $this->base->ecrire("UPDATE reservation SET statut = 'payee', code = ? WHERE id = ?", [$code, $reservationId]);
        $billet = $this->billet($code);
        $this->envoyerMail(
            $billet['email'],
            'Votre billet pour ' . $billet['titre'],
            "Séance du {$billet['debut']}, {$billet['salle']}, places {$billet['places']}.\nCode du billet : $code"
        );

        return $billet;
    }

    public function billet(string $code): array
    {
        return $this->base->lire(
            "SELECT reservation.id, reservation.code, reservation.montant, reservation.valide_le, reservation.seance_id,
                    utilisateur.email, film.titre, seance.debut, salle.nom AS salle,
                    GROUP_CONCAT(CONCAT(place.rangee, place.numero) ORDER BY place.rangee, place.numero SEPARATOR ', ') AS places
             FROM reservation
             JOIN utilisateur ON utilisateur.id = reservation.utilisateur_id
             JOIN seance ON seance.id = reservation.seance_id
             JOIN film ON film.id = seance.film_id
             JOIN salle ON salle.id = seance.salle_id
             JOIN reservation_place ON reservation_place.reservation_id = reservation.id
             JOIN place ON place.id = reservation_place.place_id
             WHERE reservation.code = ? AND reservation.statut = 'payee'
             GROUP BY reservation.id",
            [$code]
        )[0] ?? throw new NotFoundHttpException('Billet introuvable.');
    }

    // Contrôle à l'entrée : un billet ne passe qu'une fois, et uniquement pour sa séance.
    public function validerBillet(string $code, int $seanceId): array
    {
        $billet = $this->billet($code);
        if ($billet['seance_id'] !== $seanceId)
        {
            throw new ConflictHttpException('Ce billet concerne une autre séance.');
        }
        $billetValide = $this->base->ecrire('UPDATE reservation SET valide_le = ? WHERE code = ? AND valide_le IS NULL', [date('Y-m-d H:i:s'), $code]);
        if ($billetValide === 0)
        {
            throw new ConflictHttpException('Ce billet a déjà été validé.');
        }

        return $billet;
    }

    public function lister(int $seanceId, string $recherche = ''): array
    {
        return $this->base->lire(
            'SELECT reservation.id, utilisateur.email, reservation.statut, reservation.montant, reservation.cree_le,
                    COUNT(reservation_place.place_id) AS nombre_places
             FROM reservation
             JOIN utilisateur ON utilisateur.id = reservation.utilisateur_id
             LEFT JOIN reservation_place ON reservation_place.reservation_id = reservation.id
             WHERE reservation.seance_id = ? AND (utilisateur.email LIKE ? OR reservation.id = ?)
             GROUP BY reservation.id
             ORDER BY reservation.cree_le DESC',
            [$seanceId, "%$recherche%", (int) $recherche]
        );
    }

    // Annulation par l'administration : remboursement, remise en vente des places, e-mail au spectateur.
    public function annuler(int $reservationId): void
    {
        $reservation = $this->base->lire(
            "SELECT reservation.montant, reservation.statut, utilisateur.email
             FROM reservation
             JOIN utilisateur ON utilisateur.id = reservation.utilisateur_id
             WHERE reservation.id = ? AND reservation.statut <> 'annulee'",
            [$reservationId]
        )[0] ?? throw new NotFoundHttpException('Réservation introuvable ou déjà annulée.');

        if ($reservation['statut'] === 'payee')
        {
            $this->paiement->rembourser($reservation['montant']);
        }
        $this->liberer($reservationId);
        $this->envoyerMail($reservation['email'], 'Annulation de votre réservation', "Votre réservation n° $reservationId a été annulée et remboursée.");
    }

    private function liberer(int $reservationId): void
    {
        $this->base->ecrire('DELETE FROM reservation_place WHERE reservation_id = ?', [$reservationId]);
        $this->base->ecrire("UPDATE reservation SET statut = 'annulee' WHERE id = ?", [$reservationId]);
    }

    private function libererReservationsExpirees(): void
    {
        $limite = date('Y-m-d H:i:s', strtotime(self::DUREE_BLOCAGE));
        $expirees = $this->base->lire("SELECT id FROM reservation WHERE statut = 'en_attente' AND cree_le < ?", [$limite]);
        foreach ($expirees as $reservation)
        {
            $this->liberer($reservation['id']);
        }
    }

    private function envoyerMail(string $destinataire, string $sujet, string $texte): void
    {
        $this->messagerie->send((new Email())->from(self::EXPEDITEUR)->to($destinataire)->subject($sujet)->text($texte));
    }
}
