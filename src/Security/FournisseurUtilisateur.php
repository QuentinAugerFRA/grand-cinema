<?php

namespace App\Security;

use App\Service\BaseDeDonnees;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<Utilisateur> */
class FournisseurUtilisateur implements UserProviderInterface
{
    public function __construct(private BaseDeDonnees $base)
    {
    }

    public function loadUserByIdentifier(string $email): UserInterface
    {
        $ligne = $this->base->lire('SELECT * FROM utilisateur WHERE email = ?', [$email])[0] ?? throw new UserNotFoundException();

        return new Utilisateur($ligne['id'], $ligne['email'], $ligne['mot_de_passe'], $ligne['role']);
    }

    public function refreshUser(UserInterface $utilisateur): UserInterface
    {
        return $this->loadUserByIdentifier($utilisateur->getUserIdentifier());
    }

    public function supportsClass(string $classe): bool
    {
        return $classe === Utilisateur::class;
    }
}
