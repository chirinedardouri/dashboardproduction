<?php

namespace App\Controller;

use App\Entity\ProductionLine;
use App\Entity\ProductionSchedule;
use App\Entity\ProductionTarget;
use App\Form\ExcelUploadType;
use App\Service\ExcelProcessingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ExcelProcessingService $importService
    ) {}

    #[Route('/dashboard', name: 'dashboard')]
    public function index(): Response
    {
        $productionLines = $this->getProductionLinesWithData();
        $shiftData = $this->getShiftData();
        
        return $this->render('dashboard/index.html.twig', [
            'productionLines' => $productionLines,
            'shiftData' => $shiftData,
            'currentDate' => new \DateTime(),
        ]);
    }

    #[Route('/dashboard/api/production-data', name: 'api_production_data')]
    public function getProductionData(): JsonResponse
    {
        $data = $this->getProductionLinesWithData();
        return new JsonResponse($data);
    }

    #[Route('/dashboard/api/shift-data', name: 'api_shift_data')]
    public function fetchShiftData(): JsonResponse
    {
        $data = $this->getShiftData();
        return new JsonResponse($data);
    }

    // GET route - Display the upload form
    #[Route('/dashboard/import', name: 'dashboard_import', methods: ['GET'])]
    public function showImportForm(): Response
    {
        $form = $this->createForm(ExcelUploadType::class);
        
        return $this->render('dashboard/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
#[Route('/dashboard/debug-excel', name: 'debug_excel', methods: ['GET', 'POST'])]
public function debugExcel(Request $request): Response
{
    $form = $this->createForm(ExcelUploadType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $form->get('excelFile')->getData();

        if ($uploadedFile) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($uploadedFile->getRealPath());
                $worksheet   = $spreadsheet->getActiveSheet();
                $data        = $worksheet->toArray();

                // Afficher les 10 premières lignes
                $debugData = [];
                for ($i = 0; $i < min(10, count($data)); $i++) {
                    $debugData[] = [
                        'row'  => $i,
                        'data' => $data[$i],
                    ];
                }

                // Vérifie les BRAS existants
                $brasNames = array_map(
                    fn ($b) => $b->getName(),
                    $this->entityManager->getRepository(\App\Entity\Bras::class)->findAll()
                );

                return $this->render('dashboard/debug.html.twig', [
                    'debugData' => $debugData,
                    'brasNames' => $brasNames,
                    'totalRows' => count($data),
                    'form'      => $form->createView(),
                ]);

            } catch (\Throwable $e) {
                $this->addFlash('error', 'Error reading Excel file: '.$e->getMessage());
            }
        }
    }

    // GET initial ou formulaire invalide
    return $this->render('dashboard/debug.html.twig', [
        'form' => $form->createView(),
    ]);
}


    // Keep the JSON API endpoint for AJAX uploads if needed
    #[Route('/dashboard/api/import', name: 'api_dashboard_import', methods: ['POST'])]
    public function importExcelApi(Request $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('excel_file');
        $importType = $request->request->get('import_type', 'production');
        
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], 400);
        }
        
        if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls'])) {
            return new JsonResponse(['error' => 'Invalid file format'], 400);
        }
        
        try {
            $filePath = $file->getRealPath();
            
            if ($importType === 'schedule') {
                $result = $this->importService->importScheduleFromExcel($filePath);
            } else {
                $result = $this->importService->importProductionData($filePath);
            }
            
            return new JsonResponse([
                'success' => true,
                'imported' => $result['imported'],
                'errors' => $result['errors']
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/dashboard/details/{id}', name: 'dashboard_details')]
    public function lineDetails(int $id): Response
    {
        $line = $this->entityManager->getRepository(ProductionLine::class)->find($id);
        
        if (!$line) {
            throw $this->createNotFoundException('Production line not found');
        }
        
        $targets = $this->entityManager->getRepository(ProductionTarget::class)
                                      ->findBy(['productionLine' => $line], ['targetDate' => 'DESC'], 10);
        
        return $this->render('dashboard/details.html.twig', [
            'line' => $line,
            'targets' => $targets,
        ]);
    }

    private function getProductionLinesWithData(): array
    {
        $lines = $this->entityManager->getRepository(ProductionLine::class)->findBy(['isActive' => true]);
        $data = [];
        
        foreach ($lines as $line) {
            $targets = $this->entityManager->getRepository(ProductionTarget::class)
                                          ->findBy(['productionLine' => $line], ['createdAt' => 'DESC'], 3);
            
            $totalTarget = 0;
            $totalActual = 0;
            
            foreach ($targets as $target) {
                $totalTarget += $target->getTargetQuantity();
                $totalActual += $target->getActualQuantity();
            }
            
            $data[] = [
                'id' => $line->getId(),
                'name' => $line->getName(),
                'type' => $line->getLineType(),
                'capacity' => $line->getCapacity(),
                'totalTarget' => $totalTarget,
                'totalActual' => $totalActual,
                'percentage' => $totalTarget > 0 ? round(($totalActual / $totalTarget) * 100, 2) : 0,
                'targets' => array_map(function($target) {
                    return [
                        'shift' => $target->getShift(),
                        'target' => $target->getTargetQuantity(),
                        'actual' => $target->getActualQuantity(),
                        'percentage' => $target->getAchievementPercentage(),
                        'date' => $target->getTargetDate()->format('Y-m-d')
                    ];
                }, $targets)
            ];
        }
        
        return $data;
    }

    private function getShiftData(): array
    {
        $today = new \DateTime();
        $targets = $this->entityManager->getRepository(ProductionTarget::class)
                                      ->findBy(['targetDate' => $today]);
        
        $shiftSummary = [
            'Matin' => ['planned' => 0, 'realized' => 0],
            'AM' => ['planned' => 0, 'realized' => 0],
            'Nuit' => ['planned' => 0, 'realized' => 0],
        ];
        
        foreach ($targets as $target) {
            $shift = $target->getShift();
            if (isset($shiftSummary[$shift])) {
                $shiftSummary[$shift]['planned'] += $target->getTargetQuantity();
                $shiftSummary[$shift]['realized'] += $target->getActualQuantity();
            }
        }
        
        return $shiftSummary;
    }
}