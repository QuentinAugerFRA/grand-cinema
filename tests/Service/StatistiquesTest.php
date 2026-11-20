<?php

namespace App\Tests\Service;

use App\Service\Statistiques;

class StatistiquesTest extends ServiceTestCase
{
    private Statistiques $statistiques;
    private string $jour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statistiques = new Statistiques($this->base);
        $seance = $this->base->lire('SELECT id, debut FROM seance WHERE id = ?', [$this->seanceAVenir()])[0];
        $this->jour = substr($seance['debut'], 0, 10);

        // 30 places vendues sur les 300 de la salle 1
        $this->base->ecrire("INSERT INTO reservation (utilisateur_id, seance_id, statut, montant, cree_le) VALUES (?, ?, 'payee', 270, NOW())", [self::SPECTATEUR_ID, $seance['id']]);
        $this->base->ecrire(
            'INSERT INTO reservation_place (reservation_id, seance_id, place_id) SELECT ?, ?, id FROM place WHERE salle_id = ? LIMIT 30',
            [$this->base->dernierIdentifiant(), $seance['id'], self::SALLE_ID]
        );
    }

    // Le taux de remplissage est calculé par séance.
    public function testRemplissageParSeance(): void
    {
        $seance = $this->statistiques->remplissage($this->jour, $this->jour)[0];

        $this->assertSame(30, $seance['places_vendues']);
        $this->assertSame(10.0, $seance['taux']);
    }

    // Le taux de remplissage est calculé par salle.
    public function testRemplissageParSalle(): void
    {
        $salles = $this->statistiques->remplissage($this->jour, $this->jour, true);

        $this->assertCount(10, $salles);
        $this->assertSame('Salle 1', $salles[0]['salle']);
        $this->assertGreaterThan(0, $salles[0]['taux']);
    }

    // L'affluence est donnée par jour et par heure.
    public function testAffluence(): void
    {
        $creneaux = $this->statistiques->affluence($this->jour, $this->jour);

        $this->assertSame(30, array_sum(array_column($creneaux, 'places_reservees')));
        $this->assertSame(0, $creneaux[0]['annee_precedente']);
    }
}
