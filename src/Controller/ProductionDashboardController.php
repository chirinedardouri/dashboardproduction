<?php

namespace App\Controller;

use App\Entity\ShiftProduction;
use App\Entity\Bras;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductionDashboardController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/dashboard', name: 'production_dashboard')]
    public function dashboard(Request $request): Response
    {
        // Get available dates from the database
        $availableDates = $this->entityManager->getRepository(ShiftProduction::class)
            ->createQueryBuilder('sp')
            ->select('DISTINCT sp.date')
            ->where('sp.valid = true AND sp.deleted = false')
            ->orderBy('sp.date', 'DESC')
            ->getQuery()
            ->getResult();

        // Get selected date from request or use the most recent date
        $selectedDate = $request->query->get('date');
        if (!$selectedDate && !empty($availableDates)) {
            $selectedDate = $availableDates[0]['date']->format('Y-m-d');
        }

        $statistics = [];
        if ($selectedDate) {
            $statistics = $this->getProductionStatistics($selectedDate);
        }

        return $this->render('admin/production.html.twig', [
            'availableDates' => $availableDates,
            'selectedDate' => $selectedDate,
            'statistics' => $statistics
        ]);
    }

    private function getProductionStatistics(string $date): array
    {
        // Get all production data for the selected date
        $productions = $this->entityManager->getRepository(ShiftProduction::class)
            ->createQueryBuilder('sp')
            ->select('sp', 'b')
            ->leftJoin('sp.bras', 'b')
            ->where('sp.date = :date')
            ->andWhere('sp.valid = true AND sp.deleted = false')
            ->setParameter('date', new \DateTime($date))
            ->orderBy('b.nom', 'ASC')
            ->addOrderBy('sp.posteType', 'ASC')
            ->getQuery()
            ->getResult();

        $statistics = [];
        
        foreach ($productions as $production) {
            $brasName = $production->getBras()->getNom();
            
            if (!isset($statistics[$brasName])) {
                $statistics[$brasName] = [
                    'bras' => $production->getBras(),
                    'totalTarget' => 0,
                    'totalRealised' => 0,
                    'percentage' => 0,
                    'shifts' => []
                ];
            }

            $statistics[$brasName]['totalTarget'] += $production->getTargetParPoste();
            $statistics[$brasName]['totalRealised'] += $production->getRealiseParPoste();
            
            $statistics[$brasName]['shifts'][] = [
                'posteType' => $production->getPosteType(),
                'ref' => $production->getRef(),
                'target' => $production->getTargetParPoste(),
                'realised' => $production->getRealiseParPoste(),
                'cadenceHoraire' => $production->getCadenceHoraire(),
                'objectifParPoste' => $production->getObjectifParPoste()
            ];
        }

        // Calculate percentages
        foreach ($statistics as &$stat) {
            if ($stat['totalTarget'] > 0) {
                $stat['percentage'] = round(($stat['totalRealised'] / $stat['totalTarget']) * 100, 1);
            }
        }

        return $statistics;
    }

    #[Route('/dashboard/details/{brasId}/{date}', name: 'production_details')]
    public function details(int $brasId, string $date): Response
    {
        // This will be implemented later for the details button
        $bras = $this->entityManager->getRepository(Bras::class)->find($brasId);
        
        $productions = $this->entityManager->getRepository(ShiftProduction::class)
            ->createQueryBuilder('sp')
            ->where('sp.bras = :bras')
            ->andWhere('sp.date = :date')
            ->andWhere('sp.valid = true AND sp.deleted = false')
            ->setParameter('bras', $bras)
            ->setParameter('date', new \DateTime($date))
            ->orderBy('sp.posteType', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/main.html.twig', [
            'bras' => $bras,
            'date' => $date,
            'productions' => $productions
        ]);
    }
}