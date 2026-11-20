<?php

namespace App\Controller\Admin;

use App\Service\Reservations;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class ReservationController extends AbstractController
{
    public function __construct(private Reservations $reservations)
    {
    }

    #[Route('/reservations', methods: ['GET'])]
    public function lister(Request $requete): JsonResponse
    {
        return $this->json($this->reservations->lister($requete->query->getInt('seanceId'), $requete->query->getString('recherche')));
    }

    #[Route('/reservations/{reservationId}/annulation', methods: ['POST'])]
    public function annuler(int $reservationId): JsonResponse
    {
        $this->reservations->annuler($reservationId);

        return $this->json(null, 204);
    }

    #[Route('/billets/{code}/validation', methods: ['POST'])]
    public function validerBillet(string $code, Request $requete): JsonResponse
    {
        return $this->json($this->reservations->validerBillet($code, (int) ($requete->toArray()['seanceId'] ?? 0)));
    }
}
