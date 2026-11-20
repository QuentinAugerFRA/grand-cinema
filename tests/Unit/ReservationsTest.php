<?php

namespace App\Tests\Unit;

use App\Service\BaseDeDonnees;
use App\Service\Paiement;
use App\Service\Reservations;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mailer\MailerInterface;

class ReservationsTest extends TestCase
{
    // Une réservation contient de 1 à 10 places.
    #[TestWith([0])]
    #[TestWith([11])]
    public function testNombreDePlaces(int $nombrePlaces): void
    {
        $reservations = new Reservations($this->createStub(BaseDeDonnees::class), new Paiement(), $this->createStub(MailerInterface::class));

        $this->expectException(BadRequestHttpException::class);
        $reservations->reserver(1, 1, $nombrePlaces > 0 ? range(1, $nombrePlaces) : []);
    }
}
