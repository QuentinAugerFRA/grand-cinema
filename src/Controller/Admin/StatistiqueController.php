<?php

namespace App\Controller\Admin;

use App\Service\Statistiques;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/statistiques')]
class StatistiqueController extends AbstractController
{
    public function __construct(private Statistiques $statistiques)
    {
    }

    #[Route('/remplissage', methods: ['GET'])]
    public function remplissage(Request $requete): Response
    {
        [$debut, $fin] = $this->periode($requete);
        $lignes = $this->statistiques->remplissage($debut, $fin, $requete->query->get('regroupement') === 'salle');
        if ($requete->query->get('format') !== 'csv')
        {
            return $this->json($lignes);
        }

        return new Response($this->statistiques->exporterCsv($lignes), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="remplissage.csv"',
        ]);
    }

    #[Route('/affluence', methods: ['GET'])]
    public function affluence(Request $requete): Response
    {
        return $this->json($this->statistiques->affluence(...$this->periode($requete)));
    }

    // Par défaut : les 30 prochains jours.
    private function periode(Request $requete): array
    {
        return [
            $requete->query->getString('debut', date('Y-m-d')),
            $requete->query->getString('fin', date('Y-m-d', strtotime('+30 days'))),
        ];
    }
}
