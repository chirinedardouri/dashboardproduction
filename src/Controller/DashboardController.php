<?php

namespace App\Controller;
use App\Service\ExcelParser;
use Psr\Log\LoggerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Entity\Bras;
use App\Entity\ProductionFile;
use App\Entity\ShiftProduction;
use App\Entity\User;
use App\Entity\ProductionLine;
use App\Entity\ProductionSchedule;
use App\Entity\ProductionTarget;
use App\Repository\BrasRepository;
use App\Repository\ProductionLineRepository;
use App\Repository\ProductionTargetRepository;
use App\Repository\ProductionScheduleRepository;
use App\Repository\ProductionFileRepository;
use App\Repository\ShiftProductionRepository;   
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
    #[Route('/dashboard/debug-excel', name: 'debug_excel', methods: ['GET', 'POST'], requirements: ['_locale' => 'en|fr'])]
    #[Route('/dashboard/bras-preview', name: 'bras_preview', methods: ['GET'], requirements: ['_locale' => 'en|fr'])]
    public function debugExcel(Request $request, ExcelParser $excelParser, LoggerInterface $logger, BrasRepository $brasRepository): Response
    {
        $logger->info('Entering debugExcel controller');
        $brasNames = $brasRepository->findAllValidNames();
    
        if ($request->attributes->get('_route') === 'bras_preview') {
            $logger->info('Handling BRAS preview request');
            $brasList = $brasRepository->findAllValidNames();
            return new JsonResponse(['success' => true, 'brasList' => $brasList]);
        }
    
        if ($request->isMethod('POST')) {
            $logger->info('Processing POST request');
            $file = $request->files->get('excelFile');
            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                $logger->error('No file uploaded or upload error: ' . ($file ? $file->getErrorMessage() : 'No file'));
                return new JsonResponse(['success' => false, 'message' => 'No file uploaded or upload error: ' . ($file ? $file->getErrorMessage() : 'No file')]);
            }
    
            $filePath = $file->getPathname();
            $isPreview = $request->query->get('preview') === 'true';
    
            $logger->info("File received, Preview: $isPreview, FilePath: $filePath");
    
            try {
                if ($isPreview) {
                    $logger->info('Handling preview request');
                    $spreadsheet = IOFactory::load($filePath);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $highestRow = min($worksheet->getHighestRow(), 350);
                    $highestColumn = min($worksheet->getHighestColumn(), 'BM');
                    $rawData = [];
                    for ($row = 4; $row <= min($highestRow, 350); $row++) {
                        $rowData = $worksheet->rangeToArray("A$row:$highestColumn$row", null, true, true, true)[$row];
                        $rawData[] = array_values($rowData);
                    }
    
                    $result = $excelParser->parseExcel($filePath, true);
                    if (!$result['success']) {
                        $logger->error('Preview failed: ' . ($result['message'] ?? 'Unknown error'));
                        return new JsonResponse(['success' => false, 'message' => $result['message'] ?? 'Failed to preview file']);
                    }
    
                    $weekName = trim($worksheet->getCell('A1')->getValue() ?? 'Unknown Week');
                    $previewData = [
                        'totalRows' => $highestRow,
                        'lastWeek' => [
                            'name' => $weekName,
                            'dateRange' => $result['dateRange'],
                            'data' => $result['data']
                        ],
                        'brasNames' => $result['brasNames'],
                        'rawData' => array_slice($rawData, 0, 20)
                    ];
    
                    $logger->info('Preview successful, Week Name: ' . $weekName . ', Date Range: ' . json_encode($previewData['lastWeek']['dateRange']));
                    return new JsonResponse(['success' => true, 'totalRows' => $highestRow, 'lastWeek' => $previewData['lastWeek'], 'brasNames' => $previewData['brasNames'], 'rawData' => $previewData['rawData']]);
                } else {
                    $logger->info('Handling upload request');
                    if ($excelParser->parseExcel($filePath, false, $file)) {
                        $this->addFlash('success', 'File uploaded and saved successfully');
                        $logger->info('Upload successful');
                        return new JsonResponse(['success' => true]);
                    } else {
                        $logger->error('Failed to process file');
                        return new JsonResponse(['success' => false, 'message' => 'Failed to process file']);
                    }
                }
            } catch (\Exception $e) {
                $logger->error('Exception during processing: ' . $e->getMessage());
                return new JsonResponse(['success' => false, 'message' => 'Error during processing: ' . $e->getMessage()]);
            }
        }
    
        $logger->info('Rendering debug_excel template');
        return $this->render('dashboard/debug_excel.html.twig', [
            'brasNames' => $brasNames
        ]);
    }
    #[Route('/dashboard/seed-bras', name: 'seed_bras', requirements: ['_locale' => 'en|fr'])]
    public function seedBras(BrasRepository $brasRepository): Response
    {
        $brasNames = [
            'PCBA USER',
            'PCBA MEAS',
            'Finition',
            'Finition (2éme chaine)',
            'RT',
            'RN',
            'Masquage pelable + percage corks',
            'ETUVE 24h'
        ];

        $createdCount = 0;

        foreach ($brasNames as $name) {
            $existing = $brasRepository->findOneBy(['nom' => $name]);
            if (!$existing) {
                $bras = new Bras();
                $bras->setNom($name);
                $bras->setValid(true);
                $bras->setDeleted(false);
                $brasRepository->saveBras($bras);
                $createdCount++;
            }
        }

        $this->addFlash('success', "Created $createdCount BRAS entities");
        return $this->redirectToRoute('debug_excel', ['_locale' => $this->getParameter('kernel.default_locale')]);
    }
/* #[Route('/dashboard/seed-bras', name: 'seed_bras')]
public function seedBras(): Response
{
    // Create BRAS entities based on your Excel file
    $brasNames = [
        'PCBA USER',
        'PCBA MEAS',
        'Finition',
        'Finition (2éme chaine)',
        'RT',
        'RN',
        'Masquage pelable + percage corks'
    ];
    
    $createdCount = 0;
    
    foreach ($brasNames as $name) {
        $existing = $this->entityManager->getRepository(\App\Entity\Bras::class)
            ->findOneBy(['name' => $name]);
        
        if (!$existing) {
            $bras = new \App\Entity\Bras();
            $bras->setName($name);
            // Set other required properties if any
            $this->entityManager->persist($bras);
            $createdCount++;
        }
    }
    
    $this->entityManager->flush();
    
    $this->addFlash('success', "Created $createdCount BRAS entities");
    
    return $this->redirectToRoute('dashboard_import');
} */

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