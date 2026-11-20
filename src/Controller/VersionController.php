<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class VersionController extends AbstractController
{
    #[Route('/api/version', methods: ['GET'])]
    public function version(): JsonResponse
    {
        return $this->json(['version' => $this->getParameter('version_application')]);
    }
}
