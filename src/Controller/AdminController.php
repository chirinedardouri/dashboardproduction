<?php

namespace App\Controller;

use App\Entity\ShiftProduction;
use App\Entity\Bras;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route(path: '/admin', name: 'app_admin_index')]
    public function index(Request $request): Response
    {
        $availableDates = $this->entityManager->getRepository(ShiftProduction::class)
            ->createQueryBuilder('sp')
            ->select('DISTINCT sp.date')
            ->where('sp.valid = true AND sp.deleted = false')
            ->orderBy('sp.date', 'DESC')
            ->getQuery()
            ->getResult();

        $selectedDate = $request->query->get('date');
        if (!$selectedDate && !empty($availableDates)) {
            $selectedDate = $availableDates[0]['date']->format('Y-m-d');
        }

        $statistics = [];
        if ($selectedDate) {
            $statistics = $this->getProductionStatistics($selectedDate);
        }

        return $this->render('admin/main.html.twig', [
            'availableDates' => $availableDates,
            'selectedDate' => $selectedDate,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/admin/details/{brasId}/{date}', name: 'production_details')]
    public function details(int $brasId, string $date): Response
    {
        $bras = $this->entityManager->getRepository(Bras::class)->find($brasId);
        if (!$bras) {
            throw $this->createNotFoundException('Bras not found');
        }

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

        return $this->render('admin/production_details.html.twig', [
            'bras' => $bras,
            'date' => $date,
            'productions' => $productions,
        ]);
    }

    private function getProductionStatistics(string $date): array
    {
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
                    'shifts' => [],
                ];
            }

            $statistics[$brasName]['totalTarget'] += $production->getTargetParPoste() ?? 0;
            $statistics[$brasName]['totalRealised'] += $production->getRealiseParPoste() ?? 0;

            $statistics[$brasName]['shifts'][] = [
                'posteType' => $production->getPosteType(),
                'ref' => $production->getRef(),
                'target' => $production->getTargetParPoste() ?? 0,
                'realised' => $production->getRealiseParPoste() ?? 0,
                'cadenceHoraire' => $production->getCadenceHoraire() ?? 0,
                'objectifParPoste' => $production->getObjectifParPoste() ?? 0,
            ];
        }

        foreach ($statistics as &$stat) {
            $totalTarget = $stat['totalTarget'];
            $stat['percentage'] = ($totalTarget > 0) ? round(($stat['totalRealised'] / $totalTarget) * 100, 1) : 0;
        }

        return $statistics;
    }

    public function realIndex(): RedirectResponse
    {
        return $this->redirectToRoute('app_admin_index');
    }
}