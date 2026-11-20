<?php

namespace App\Service;

use PDO;
use Throwable;

class BaseDeDonnees
{
    public function __construct(private PDO $connexion)
    {
    }

    public function lire(string $requete, array $parametres = []): array
    {
        $resultat = $this->connexion->prepare($requete);
        $resultat->execute($parametres);

        return $resultat->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ecrire(string $requete, array $parametres = []): int
    {
        $resultat = $this->connexion->prepare($requete);
        $resultat->execute($parametres);

        return $resultat->rowCount();
    }

    public function dernierIdentifiant(): int
    {
        return (int) $this->connexion->lastInsertId();
    }

    public function transaction(callable $traitement): mixed
    {
        $this->connexion->beginTransaction();
        try
        {
            $resultat = $traitement();
            $this->connexion->commit();

            return $resultat;
        }
        catch (Throwable $erreur)
        {
            $this->connexion->rollBack();
            throw $erreur;
        }
    }
}
