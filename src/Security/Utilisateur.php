<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        public int $id,
        public string $email,
        public string $motDePasse,
        public string $role
    )
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->motDePasse;
    }

    public function getRoles(): array
    {
        return array_unique([$this->role, 'ROLE_USER']);
    }

    public function eraseCredentials(): void
    {
    }
}
