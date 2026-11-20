<?php

namespace App\Service;

class Statistiques
{
    public function __construct(private BaseDeDonnees $base)
    {
    }

    // Taux de remplissage sur une période, par séance ou par salle.
    public function remplissage(string $debut, string $fin, bool $parSalle = false): array
    {
        $colonnes = $parSalle ? 'salle.nom AS salle' : 'seance.id, film.titre, seance.debut, salle.nom AS salle';
        $regroupement = $parSalle ? 'salle.id' : 'seance.id';
        $tri = $parSalle ? 'salle.id' : 'seance.debut, salle.id';
        $lignes = $this->base->lire(
            "SELECT $colonnes,
                    COUNT(DISTINCT seance.id) * salle.nombre_rangees * salle.places_par_rangee AS capacite,
                    COUNT(reservation.id) AS places_vendues
             FROM seance
             JOIN film ON film.id = seance.film_id
             JOIN salle ON salle.id = seance.salle_id
             LEFT JOIN reservation_place ON reservation_place.seance_id = seance.id
             LEFT JOIN reservation ON reservation.id = reservation_place.reservation_id AND reservation.statut = 'payee'
             WHERE seance.debut BETWEEN ? AND ?
             GROUP BY $regroupement
             ORDER BY $tri",
            ["$debut 00:00:00", "$fin 23:59:59"]
        );

        return array_map(fn (array $ligne) => $ligne + ['taux' => round(100 * $ligne['places_vendues'] / $ligne['capacite'], 1)], $lignes);
    }

    // Places réservées par jour et par heure, comparées à la même date de l'année précédente.
    public function affluence(string $debut, string $fin): array
    {
        return $this->base->lire(
            'SELECT creneau.*,
                    (SELECT COUNT(*)
                     FROM reservation_place
                     JOIN seance ON seance.id = reservation_place.seance_id
                     WHERE DATE(seance.debut) = creneau.jour - INTERVAL 1 YEAR AND HOUR(seance.debut) = creneau.heure) AS annee_precedente
             FROM (SELECT DATE(seance.debut) AS jour, HOUR(seance.debut) AS heure, COUNT(reservation_place.place_id) AS places_reservees
                   FROM seance
                   LEFT JOIN reservation_place ON reservation_place.seance_id = seance.id
                   WHERE seance.debut BETWEEN ? AND ?
                   GROUP BY jour, heure) AS creneau
             ORDER BY creneau.jour, creneau.heure',
            ["$debut 00:00:00", "$fin 23:59:59"]
        );
    }

    public function exporterCsv(array $lignes): string
    {
        $lignesCsv = array_map(fn (array $ligne) => implode(';', $ligne), [array_keys($lignes[0] ?? []), ...$lignes]);

        return implode("\n", $lignesCsv);
    }
}
