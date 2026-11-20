<?php

namespace App\Tests\Service;

use App\Service\Programmation;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProgrammationTest extends ServiceTestCase
{
    private const FILM = ['titre' => 'Film de test', 'dureeMinutes' => 90, 'genre' => 'Drame', 'affiche' => 'https://exemple.fr/affiche.jpg'];

    private Programmation $programmation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->programmation = new Programmation($this->base);
        $this->base->ecrire('DELETE FROM film WHERE titre LIKE ?', ['Film de test%']);
    }

    // Seuls les films ayant une séance à venir sont à l'affiche.
    public function testFilmsALAffiche(): void
    {
        $this->programmation->enregistrerFilm(self::FILM);

        $this->assertCount(6, $this->programmation->filmsALAffiche());
        $this->assertCount(7, $this->programmation->films());
    }

    // Les séances à venir indiquent la salle et la capacité.
    public function testSeancesAVenir(): void
    {
        $seance = $this->programmation->seancesAVenir(1)[0];

        $this->assertStringStartsWith('Salle', $seance['salle']);
        $this->assertContains($seance['capacite'], [300, 180, 120, 70]);
        $this->assertContains($seance['nombre_blocs'], [2, 3]);
    }

    // Les 10 salles totalisent 1 700 places.
    public function testCapaciteTotale(): void
    {
        $salles = $this->programmation->salles();

        $this->assertCount(10, $salles);
        $this->assertSame('Premium / IMAX', $salles[0]['type']);
        $this->assertSame(1700, array_sum(array_column($salles, 'capacite')));
    }

    // Un film sans affiche est refusé.
    public function testFilmSansAffiche(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->programmation->enregistrerFilm(['affiche' => ''] + self::FILM);
    }

    // Un film sans réservation peut être modifié puis supprimé.
    public function testFilmModifiable(): void
    {
        $filmId = $this->programmation->enregistrerFilm(self::FILM);
        $this->programmation->enregistrerFilm(['titre' => 'Film de test modifié'] + self::FILM, $filmId);
        $this->assertContains('Film de test modifié', array_column($this->programmation->films(), 'titre'));

        $this->programmation->supprimerFilm($filmId);
        $this->assertCount(6, $this->programmation->films());
    }

    // Un film déjà réservé ne peut plus être supprimé.
    public function testFilmReserve(): void
    {
        $this->base->ecrire('INSERT INTO reservation (utilisateur_id, seance_id, montant, cree_le) VALUES (?, ?, 9, NOW())', [self::SPECTATEUR_ID, $this->seanceAVenir()]);

        $this->expectException(ConflictHttpException::class);
        $this->programmation->supprimerFilm(1);
    }

    // Une séance est planifiée dans une salle libre.
    public function testSeancePlanifiee(): void
    {
        $filmId = $this->programmation->enregistrerFilm(self::FILM);
        $seanceId = $this->programmation->planifierSeance(['filmId' => $filmId, 'salleId' => 10, 'debut' => $this->demain('10:00'), 'tarif' => 7.5]);

        $seance = $this->base->lire('SELECT * FROM seance WHERE id = ?', [$seanceId])[0];
        $this->assertSame('11:30:00', substr($seance['fin'], 11));
        $this->assertCount(7, $this->programmation->filmsALAffiche());
    }

    // Une séance est refusée si la salle est occupée sur le créneau.
    public function testSalleOccupee(): void
    {
        $this->expectException(ConflictHttpException::class);
        $this->programmation->planifierSeance(['filmId' => 2, 'salleId' => 1, 'debut' => $this->demain('15:00'), 'tarif' => 9]);
    }

    // Une séance dans le passé est refusée.
    public function testSeancePassee(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->programmation->planifierSeance(['filmId' => 2, 'salleId' => 1, 'debut' => '2020-01-01T10:00', 'tarif' => 9]);
    }

    // Une séance sur un film inconnu est refusée.
    public function testFilmInconnu(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->programmation->planifierSeance(['filmId' => 0, 'salleId' => 1, 'debut' => $this->demain('10:00'), 'tarif' => 9]);
    }

    private function demain(string $heure): string
    {
        return date('Y-m-d', strtotime('+1 day')) . 'T' . $heure;
    }
}
