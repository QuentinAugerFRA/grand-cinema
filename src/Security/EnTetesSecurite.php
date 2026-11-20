<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

// Ajoute les en-têtes de sécurité à toutes les réponses de l'API.
// Les pages statiques reçoivent les mêmes en-têtes par le fichier public/.htaccess.
class EnTetesSecurite
{
    private const EN_TETES = [
        // Le navigateur ne devine pas le type d'un fichier : une image ne peut pas être exécutée comme un script
        'X-Content-Type-Options' => 'nosniff',
        // L'application ne peut pas être affichée dans le cadre d'un autre site (détournement de clic)
        'X-Frame-Options' => 'DENY',
        // L'adresse complète de la page n'est pas transmise aux autres sites
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // Une réponse de l'API ne charge aucune ressource externe
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
    ];

    #[AsEventListener]
    public function ajouterEnTetes(ResponseEvent $evenement): void
    {
        $evenement->getResponse()->headers->add(self::EN_TETES);
    }
}
