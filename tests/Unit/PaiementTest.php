<?php

namespace App\Tests\Unit;

use App\Service\Paiement;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PaiementTest extends TestCase
{
    // Seule une carte de 16 chiffres, qui ne se termine pas par 0000, est débitée.
    #[TestWith(['4242424242424242', true])]
    #[TestWith(['4242424242420000', false])]
    #[TestWith(['1234', false])]
    #[TestWith(['abcdabcdabcdabcd', false])]
    public function testCarteValide(string $numeroCarte, bool $paiementAccepte): void
    {
        $this->assertSame($paiementAccepte, (new Paiement())->debiter($numeroCarte, 18.0));
    }

    // Un montant nul n'est ni débité ni remboursé.
    public function testMontantNul(): void
    {
        $this->assertFalse((new Paiement())->debiter('4242424242424242', 0));
        $this->assertFalse((new Paiement())->rembourser(0));
        $this->assertTrue((new Paiement())->rembourser(18.0));
    }
}
