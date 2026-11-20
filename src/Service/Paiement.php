<?php

namespace App\Service;

// Paiement simulé pour la démonstration : à remplacer par l'appel au prestataire de paiement.
class Paiement
{
    // Une carte de 16 chiffres est acceptée, sauf si elle se termine par 0000.
    public function debiter(string $numeroCarte, float $montant): bool
    {
        return $montant > 0 && preg_match('/^\d{16}$/', $numeroCarte) === 1 && !str_ends_with($numeroCarte, '0000');
    }

    public function rembourser(float $montant): bool
    {
        return $montant > 0;
    }
}
