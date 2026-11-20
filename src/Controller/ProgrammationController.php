<?php

namespace App\Controller;

use App\Service\Programmation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/films')]
class ProgrammationController extends AbstractController
{
    public function __construct(private Programmation $programmation)
    {
    }

    #[Route('', methods: ['GET'])]
    public function filmsALAffiche(): JsonResponse
    {
        return $this->json($this->programmation->filmsALAffiche());
    }

    #[Route('/{filmId}/seances', methods: ['GET'])]
    public function seancesAVenir(int $filmId): JsonResponse
    {
        return $this->json($this->programmation->seancesAVenir($filmId));
    }
}
