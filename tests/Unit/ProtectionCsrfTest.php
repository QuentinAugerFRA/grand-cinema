<?php

namespace App\Tests\Unit;

use App\Security\ProtectionCsrf;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ProtectionCsrfTest extends TestCase
{
    // Une modification sans l'en-tête X-Requested-With est refusée.
    public function testRequeteRefusee(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        (new ProtectionCsrf())->verifierOrigine($this->evenement('POST', '/api/reservations'));
    }

    // Les lectures, les pages et les modifications avec l'en-tête sont acceptées.
    #[TestWith(['POST', '/api/reservations', 'XMLHttpRequest'])]
    #[TestWith(['GET', '/api/films', null])]
    #[TestWith(['POST', '/index.html', null])]
    public function testRequeteAcceptee(string $methode, string $chemin, ?string $enTete): void
    {
        (new ProtectionCsrf())->verifierOrigine($this->evenement($methode, $chemin, $enTete));

        $this->addToAssertionCount(1);
    }

    private function evenement(string $methode, string $chemin, ?string $enTete = null): RequestEvent
    {
        $requete = Request::create($chemin, $methode);
        $requete->headers->set('X-Requested-With', $enTete);

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $requete, HttpKernelInterface::MAIN_REQUEST);
    }
}
