<?php

namespace App\Controller;

use App\Security\Utilisateur;
use App\Service\BaseDeDonnees;
use PDOException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class CompteController extends AbstractController
{
    #[Route('/inscription', methods: ['POST'])]
    public function inscrire(Request $requete, BaseDeDonnees $base, UserPasswordHasherInterface $hacheur): JsonResponse
    {
        $saisie = $requete->toArray();
        $email = filter_var($saisie['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $motDePasse = (string) ($saisie['motDePasse'] ?? '');
        if ($email === false || strlen($motDePasse) < 8)
        {
            throw new BadRequestHttpException('Adresse e-mail valide et mot de passe de 8 caractères minimum obligatoires.');
        }
        try
        {
            $motDePasseHache = $hacheur->hashPassword(new Utilisateur(0, $email, '', 'ROLE_USER'), $motDePasse);
            $base->ecrire('INSERT INTO utilisateur (email, mot_de_passe) VALUES (?, ?)', [$email, $motDePasseHache]);
        }
        catch (PDOException)
        {
            throw new ConflictHttpException('Un compte existe déjà avec cette adresse e-mail.');
        }

        return $this->json(['email' => $email], 201);
    }

    // La connexion est traitée par le pare-feu (json_login) : cette action renvoie seulement le compte connecté.
    #[Route('/connexion', methods: ['POST'])]
    #[Route('/compte', methods: ['GET'])]
    public function compte(): JsonResponse
    {
        $utilisateur = $this->getUser();

        return $this->json($utilisateur === null ? null : [
            'email' => $utilisateur->getUserIdentifier(),
            'administrateur' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }
}
