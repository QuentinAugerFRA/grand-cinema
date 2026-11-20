<?php

namespace App\Tests\Unit;

use App\Service\BaseDeDonnees;
use App\Service\Statistiques;
use PHPUnit\Framework\TestCase;

class StatistiquesTest extends TestCase
{
    private Statistiques $statistiques;

    protected function setUp(): void
    {
        $this->statistiques = new Statistiques($this->createStub(BaseDeDonnees::class));
    }

    // L'export commence par une ligne d'en-têtes.
    public function testExportCsv(): void
    {
        $csv = $this->statistiques->exporterCsv([['salle' => 'Salle 1', 'taux' => 10.0]]);

        $this->assertSame("salle;taux\nSalle 1;10", $csv);
    }

    // Sans donnée, l'export est vide.
    public function testExportCsvVide(): void
    {
        $this->assertSame('', $this->statistiques->exporterCsv([]));
    }
}
