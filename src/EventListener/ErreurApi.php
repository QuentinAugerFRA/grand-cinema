<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ErreurApi
{
    private const MESSAGES = [
        401 => 'Connexion requise.',
        403 => 'Accès refusé.',
        500 => 'Erreur interne du serveur.',
    ];

    // Toute erreur est renvoyée en JSON, sans détail technique pour une erreur interne.
    #[AsEventListener]
    public function convertirEnJson(ExceptionEvent $evenement): void
    {
        $erreur = $evenement->getThrowable();
        $codeHttp = $erreur instanceof HttpExceptionInterface ? $erreur->getStatusCode() : 500;

        $evenement->setResponse(new JsonResponse(['erreur' => self::MESSAGES[$codeHttp] ?? $erreur->getMessage()], $codeHttp));
    }
}
