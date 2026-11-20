<?php

namespace App\Tests\Unit;

use App\Security\Utilisateur;
use PHPUnit\Framework\TestCase;

class UtilisateurTest extends TestCase
{
    // Un administrateur a aussi les droits d'un spectateur.
    public function testRolesAdministrateur(): void
    {
        $administrateur = new Utilisateur(1, 'admin@grandcinema.fr', 'empreinte', 'ROLE_ADMIN');

        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $administrateur->getRoles());
    }

    // Un spectateur est identifié par son e-mail et n'a qu'un rôle.
    public function testRolesSpectateur(): void
    {
        $spectateur = new Utilisateur(2, 'spectateur@grandcinema.fr', 'empreinte', 'ROLE_USER');

        $this->assertSame('spectateur@grandcinema.fr', $spectateur->getUserIdentifier());
        $this->assertSame(['ROLE_USER'], $spectateur->getRoles());
    }
}
