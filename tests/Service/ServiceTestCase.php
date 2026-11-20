<?php

namespace App\Tests\Service;

use App\Service\BaseDeDonnees;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class ServiceTestCase extends TestCase
{
    protected const SPECTATEUR_ID = 2;
    protected const SALLE_ID = 1;

    protected BaseDeDonnees $base;

    protected function setUp(): void
    {
        $this->base = new BaseDeDonnees(new PDO($_ENV['DATABASE_DSN'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']));
        $this->base->ecrire('DELETE FROM reservation');
    }

    protected function seanceAVenir(int $salleId = self::SALLE_ID): int
    {
        return $this->base->lire('SELECT id FROM seance WHERE salle_id = ? AND debut > NOW() ORDER BY debut LIMIT 1', [$salleId])[0]['id'];
    }

    protected function places(int $nombre, int $salleId = self::SALLE_ID): array
    {
        return array_column($this->base->lire("SELECT id FROM place WHERE salle_id = ? ORDER BY rangee, numero LIMIT $nombre", [$salleId]), 'id');
    }
}
