<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

// Protection contre la falsification de requête (CSRF).
// Une requête qui modifie des données doit porter l'en-tête X-Requested-With, ajouté par le JavaScript de l'application.
// Un formulaire placé sur un autre site ne peut pas ajouter cet en-tête : sa requête est refusée.
class ProtectionCsrf
{
    // La priorité 16 fait passer ce contrôle avant le pare-feu, donc avant la connexion.
    #[AsEventListener(priority: 16)]
    public function verifierOrigine(RequestEvent $evenement): void
    {
        $requete = $evenement->getRequest();
        $modifieDesDonnees = !$requete->isMethodSafe() && str_starts_with($requete->getPathInfo(), '/api/');
        if ($modifieDesDonnees && $requete->headers->get('X-Requested-With') !== 'XMLHttpRequest')
        {
            throw new AccessDeniedHttpException();
        }
    }
}
