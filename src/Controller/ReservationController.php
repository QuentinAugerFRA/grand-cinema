<?php

namespace App\Controller;

use App\Security\Utilisateur;
use App\Service\Reservations;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
class ReservationController extends AbstractController
{
    public function __construct(private Reservations $reservations)
    {
    }

    #[Route('/seances/{seanceId}/places', methods: ['GET'])]
    public function plan(int $seanceId): JsonResponse
    {
        return $this->json($this->reservations->plan($seanceId));
    }

    #[Route('/reservations', methods: ['POST'])]
    public function reserver(Request $requete, #[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        $saisie = $requete->toArray();
        $reservation = $this->reservations->reserver($utilisateur->id, (int) ($saisie['seanceId'] ?? 0), (array) ($saisie['placeIds'] ?? []));

        return $this->json($reservation, 201);
    }

    #[Route('/reservations/{reservationId}/paiement', methods: ['POST'])]
    public function payer(int $reservationId, Request $requete, #[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        $numeroCarte = (string) ($requete->toArray()['numeroCarte'] ?? '');

        return $this->json($this->reservations->payer($reservationId, $utilisateur->id, $numeroCarte));
    }

    #[Route('/billets/{code}', methods: ['GET'])]
    public function billet(string $code): JsonResponse
    {
        return $this->json($this->reservations->billet($code));
    }
}
