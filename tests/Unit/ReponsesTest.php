<?php

namespace App\Tests\Unit;

use App\EventListener\ErreurApi;
use App\Security\EnTetesSecurite;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

class ReponsesTest extends TestCase
{
    // Chaque réponse reçoit les en-têtes de sécurité.
    public function testEnTetesSecurite(): void
    {
        $evenement = new ResponseEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, new Response());
        (new EnTetesSecurite())->ajouterEnTetes($evenement);

        $this->assertSame('nosniff', $evenement->getResponse()->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $evenement->getResponse()->headers->get('X-Frame-Options'));
    }

    // Une erreur prévue garde son code HTTP et son message.
    public function testErreurConnue(): void
    {
        $reponse = $this->reponseAUneErreur(new NotFoundHttpException('Séance introuvable.'));

        $this->assertSame(404, $reponse->getStatusCode());
        $this->assertSame(['erreur' => 'Séance introuvable.'], json_decode($reponse->getContent(), true));
    }

    // Une erreur interne ne donne aucun détail technique.
    public function testErreurInterne(): void
    {
        $reponse = $this->reponseAUneErreur(new RuntimeException('SQLSTATE[42S02] table inconnue'));

        $this->assertSame(500, $reponse->getStatusCode());
        $this->assertStringNotContainsString('SQLSTATE', $reponse->getContent());
    }

    private function reponseAUneErreur(Throwable $erreur): Response
    {
        $evenement = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $erreur);
        (new ErreurApi())->convertirEnJson($evenement);

        return $evenement->getResponse();
    }
}
