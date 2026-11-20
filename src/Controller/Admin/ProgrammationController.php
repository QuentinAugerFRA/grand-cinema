<?php

namespace App\Controller\Admin;

use App\Service\Programmation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class ProgrammationController extends AbstractController
{
    private const TYPES_AFFICHE = ['image/jpeg', 'image/png', 'image/webp'];
    private const TAILLE_MAXIMALE_AFFICHE = 2 * 1024 * 1024;

    public function __construct(private Programmation $programmation)
    {
    }

    // Enregistre l'image dans public/affiches sous un nom aléatoire et renvoie son adresse.
    #[Route('/affiches', methods: ['POST'])]
    public function envoyerAffiche(Request $requete): JsonResponse
    {
        $fichier = $requete->files->get('affiche');
        $estValide = $fichier instanceof UploadedFile && $fichier->isValid()
            && in_array($fichier->getMimeType(), self::TYPES_AFFICHE, true)
            && $fichier->getSize() <= self::TAILLE_MAXIMALE_AFFICHE;
        if (!$estValide)
        {
            throw new BadRequestHttpException('L\'affiche doit être une image JPEG, PNG ou WebP de 2 Mo maximum.');
        }
        $nom = bin2hex(random_bytes(8)) . '.' . $fichier->guessExtension();
        $fichier->move($this->getParameter('kernel.project_dir') . '/public/affiches', $nom);

        return $this->json(['affiche' => 'affiches/' . $nom], 201);
    }

    #[Route('/films', methods: ['GET'])]
    public function films(): JsonResponse
    {
        return $this->json($this->programmation->films());
    }

    #[Route('/films', methods: ['POST'])]
    public function ajouterFilm(Request $requete): JsonResponse
    {
        return $this->json(['id' => $this->programmation->enregistrerFilm($requete->toArray())], 201);
    }

    #[Route('/films/{filmId}', methods: ['PUT'])]
    public function modifierFilm(int $filmId, Request $requete): JsonResponse
    {
        return $this->json(['id' => $this->programmation->enregistrerFilm($requete->toArray(), $filmId)]);
    }

    #[Route('/films/{filmId}', methods: ['DELETE'])]
    public function supprimerFilm(int $filmId): JsonResponse
    {
        $this->programmation->supprimerFilm($filmId);

        return $this->json(null, 204);
    }

    #[Route('/salles', methods: ['GET'])]
    public function salles(): JsonResponse
    {
        return $this->json($this->programmation->salles());
    }

    #[Route('/seances', methods: ['POST'])]
    public function planifierSeance(Request $requete): JsonResponse
    {
        return $this->json(['id' => $this->programmation->planifierSeance($requete->toArray())], 201);
    }
}
