<?php

namespace App\Service;

use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Programmation
{
    public function __construct(private BaseDeDonnees $base)
    {
    }

    public function filmsALAffiche(): array
    {
        return $this->base->lire(
            'SELECT DISTINCT film.* FROM film JOIN seance ON seance.film_id = film.id WHERE seance.debut > ? ORDER BY film.titre',
            [date('Y-m-d H:i:s')]
        );
    }

    public function seancesAVenir(int $filmId): array
    {
        return $this->base->lire(
            'SELECT seance.id, seance.debut, seance.tarif, salle.nom AS salle, salle.type AS type_salle, salle.nombre_blocs,
                    salle.nombre_rangees * salle.places_par_rangee AS capacite,
                    (SELECT COUNT(*) FROM reservation_place WHERE reservation_place.seance_id = seance.id) AS places_occupees
             FROM seance
             JOIN salle ON salle.id = seance.salle_id
             WHERE seance.film_id = ? AND seance.debut > ?
             ORDER BY seance.debut',
            [$filmId, date('Y-m-d H:i:s')]
        );
    }

    public function films(): array
    {
        return $this->base->lire('SELECT * FROM film ORDER BY titre');
    }

    public function salles(): array
    {
        return $this->base->lire('SELECT id, nom, type, nombre_rangees * places_par_rangee AS capacite FROM salle ORDER BY id');
    }

    public function enregistrerFilm(array $film, ?int $filmId = null): int
    {
        $valeurs = [trim($film['titre'] ?? ''), (int) ($film['dureeMinutes'] ?? 0), trim($film['genre'] ?? ''), trim($film['affiche'] ?? '')];
        if (in_array('', $valeurs, true) || $valeurs[1] <= 0)
        {
            throw new BadRequestHttpException('Le titre, la durée, le genre et l\'affiche sont obligatoires.');
        }
        if ($filmId === null)
        {
            $this->base->ecrire('INSERT INTO film (titre, duree_minutes, genre, affiche) VALUES (?, ?, ?, ?)', $valeurs);

            return $this->base->dernierIdentifiant();
        }
        $this->refuserSiFilmReserve($filmId);
        $this->base->ecrire('UPDATE film SET titre = ?, duree_minutes = ?, genre = ?, affiche = ? WHERE id = ?', [...$valeurs, $filmId]);

        return $filmId;
    }

    public function supprimerFilm(int $filmId): void
    {
        $this->refuserSiFilmReserve($filmId);
        $this->base->ecrire('DELETE FROM film WHERE id = ?', [$filmId]);
    }

    public function planifierSeance(array $seance): int
    {
        $film = $this->base->lire('SELECT duree_minutes FROM film WHERE id = ?', [$seance['filmId'] ?? 0])[0] ?? throw new NotFoundHttpException('Film introuvable.');
        $debut = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $seance['debut'] ?? '');
        $tarif = (float) ($seance['tarif'] ?? 0);
        $salleId = (int) ($seance['salleId'] ?? 0);
        if ($debut === false || $debut < new DateTimeImmutable() || $tarif <= 0)
        {
            throw new BadRequestHttpException('La date doit être à venir et le tarif supérieur à zéro.');
        }
        $debutSeance = $debut->format('Y-m-d H:i:00');
        $finSeance = $debut->modify("+{$film['duree_minutes']} minutes")->format('Y-m-d H:i:00');
        if ($this->base->lire('SELECT id FROM seance WHERE salle_id = ? AND debut < ? AND fin > ?', [$salleId, $finSeance, $debutSeance]))
        {
            throw new ConflictHttpException('La salle est déjà occupée sur ce créneau.');
        }
        $this->base->ecrire(
            'INSERT INTO seance (film_id, salle_id, debut, fin, tarif) SELECT ?, id, ?, ?, ? FROM salle WHERE id = ?',
            [$seance['filmId'], $debutSeance, $finSeance, $tarif, $salleId]
        ) ?: throw new NotFoundHttpException('Salle introuvable.');

        return $this->base->dernierIdentifiant();
    }

    private function refuserSiFilmReserve(int $filmId): void
    {
        $reservations = $this->base->lire(
            'SELECT reservation.id FROM reservation JOIN seance ON seance.id = reservation.seance_id WHERE seance.film_id = ? LIMIT 1',
            [$filmId]
        );
        if ($reservations)
        {
            throw new ConflictHttpException('Ce film a déjà des réservations : il ne peut plus être modifié ni supprimé.');
        }
    }
}
