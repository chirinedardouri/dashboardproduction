<?php
namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ProductionFile;
use App\Entity\Bras;
use App\Entity\ShiftProduction;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\SecurityBundle\Security;

class ExcelParser
{
    private $logger;
    private $entityManager;
    private $productionFileId;
    private Security $security;
    

    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager,Security $security)
    {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        date_default_timezone_set('UTC');
         $this->security = $security; 

    }

    public function parseExcel($filePath, $preview = false, ?UploadedFile $uploadedFile = null)
    {
        $this->logger->info("Starting Excel parsing for file: $filePath, Preview: " . ($preview ? 'true' : 'false'));
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $this->productionFileId = $preview ? null : $this->createProductionFileRecord($filePath, $uploadedFile);

            $highestRow = $worksheet->getHighestRow();
            $highestColumn = min($worksheet->getHighestColumn(), 'BM');

            // Get week name from row 1, column 1
            $weekName = trim($worksheet->getCell('A1')->getValue() ?? 'Unknown Week');
            $this->logger->info("Extracted week name: $weekName");

            // Get date range from row 1 (columns D, J, P, etc., starting from column 4)
            $dateSerials = [];
            $col = 4; // Start at column D (45803)
            while ($dateSerial = $worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1')->getValue()) {
                if (is_numeric($dateSerial)) {
                    $dateSerials[] = $dateSerial;
                }
                $col += 9; // 3 shifts x 3 columns (REF, Planifié, Réalisé)
            }
            $firstDate = !empty($dateSerials) ? $this->parseCellDate(min($dateSerials), 1, 4) : null;
            $lastDate = !empty($dateSerials) ? $this->parseCellDate(max($dateSerials), 1, $col - 9) : null;

            // Collect unique BRAS names from column A (rows 4 to highestRow)
            $brasNames = [];
            for ($row = 4; $row <= $highestRow; $row++) {
                $brasName = trim($worksheet->getCell('A' . $row)->getValue() ?? '');
                if (!empty($brasName)) {
                    $brasNames[$brasName] = true;
                }
            }
            $uniqueBrasNames = array_keys($brasNames);

            $shiftsProcessed = [];
            $previewData = [];
            $rowCount = 0;

            for ($row = 4; $row <= $highestRow; $row++) {
                $rowData = $worksheet->rangeToArray("A$row:$highestColumn$row", null, true, true, true)[$row] ?? [];
                $brasName = trim($rowData['A'] ?? '');
                if (empty($brasName)) {
                    $this->logger->debug("Skipping empty row $row");
                    continue;
                }

                $bras = $preview ? $brasName : $this->getBras($brasName);
                if (!$bras) {
                    $this->logger->error("Skipping row $row: Invalid bras name '$brasName'");
                    continue;
                }
                $brasId = $preview ? $brasName : $bras->getId();

                $cadenceHoraire = $this->parseNumeric($rowData['B'] ?? null, $row, 'B');
                $objectifParPoste = $this->parseNumeric($rowData['C'] ?? null, $row, 'C');

                $col = 4;
                foreach ($dateSerials as $dateIndex => $dateSerial) {
                    $date = $this->parseCellDate($dateSerial, 1, $col);
                    if (!$date) {
                        $this->logger->warning("Invalid date at row $row, column $col");
                        $col += 9;
                        continue;
                    }

                    for ($shift = 1; $shift <= 3; $shift++) {
                        $refCol = $col + ($shift - 1) * 3;
                        $planifieCol = $refCol + 1;
                        $realiseCol = $refCol + 2;

                        $ref = $this->parseReference($worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($refCol) . $row)->getValue() ?? null, $row, $refCol);
                        $planifie = $this->parseNumeric($worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($planifieCol) . $row)->getValue() ?? null, $row, $planifieCol);
                        $realise = $this->parseNumeric($worksheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($realiseCol) . $row)->getValue() ?? null, $row, $realiseCol);

                        $this->logger->debug("Row $row, Shift P$shift, RefCol $refCol, PlanifieCol $planifieCol, RealiseCol $realiseCol, Ref: $ref, Planifie: $planifie, Realise: $realise");

                        if (!$ref || $planifie === null || $realise === null) {
                            $this->logger->warning("Skipping shift at row $row, column $refCol: Invalid ref='$ref', planifie=$planifie, realise=$realise");
                            continue;
                        }

                        $posteType = 'P' . $shift;
                        $shiftKey = "$brasId-$this->productionFileId-$date-$posteType-$ref";
                        if (isset($shiftsProcessed[$shiftKey])) {
                            $this->logger->warning("Skipping duplicate shift in row $row: bras_id=$brasId, date=$date, poste=$posteType, ref=$ref");
                            continue;
                        }

                        if ($preview) {
                            $previewData[] = [
                                'bras' => $brasName,
                                'date' => $date,
                                'shift' => $posteType,
                                'ref' => $ref,
                                'planifie' => $planifie,
                                'realise' => $realise
                            ];
                        } else {
                            $this->insertShiftProduction($bras, $date, $posteType, $ref, $planifie, $realise, $cadenceHoraire, $objectifParPoste);
                            $rowCount++;
                        }
                        $shiftsProcessed[$shiftKey] = true;

                        $this->logger->info("Parsed shift: Poste=$posteType, Ref=$ref, Planifié=$planifie, Réalisé=$realise, Date=$date, Row=$row");
                    }
                    $col += 9;
                }
            }

            if (!$preview) {
                $this->entityManager->flush();
                $this->logger->info("Saved $rowCount ShiftProduction records");
            }

            return $preview ? ['success' => true, 'data' => $previewData, 'brasNames' => $uniqueBrasNames, 'dateRange' => [$firstDate, $lastDate]] : ['success' => true, 'count' => $rowCount];
        } catch (\Exception $e) {
            $this->logger->error("Failed to parse Excel file: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function parseCellDate($cellValue, $row, $column)
    {
        if (empty($cellValue)) {
            $this->logger->warning("Empty date value at row $row, column $column");
            return null;
        }

        if (is_numeric($cellValue)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);
                if ($date->format('Y') >= 2000) {
                    $this->logger->info("Parsed Excel numeric date: $cellValue to " . $date->format('Y-m-d') . " at row $row, column $column");
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                $this->logger->error("Failed to parse numeric date: $cellValue at row $row, column $column - {$e->getMessage()}");
                return null;
            }
        }
        $this->logger->warning("Skipping non-numeric date value: '$cellValue' at row $row, column $column");
        return null;
    }

    private function parseReference($cellValue, $row, $column)
    {
        $cellValue = trim($cellValue ?? '');
        if (empty($cellValue)) {
            $this->logger->warning("Empty reference value at row $row, column $column");
            return null;
        }
        if (preg_match('/^PRF\d+$|^PSF\d+$/i', $cellValue)) {
            return strtoupper($cellValue);
        }
        $this->logger->warning("Invalid reference format: '$cellValue' at row $row, column $column");
        return null;
    }

    private function parseNumeric($cellValue, $row, $column)
    {
        $cellValue = trim($cellValue ?? '');
        if ($cellValue === '' || $cellValue === null) {
            return 0.0;
        }
        if (is_numeric($cellValue) && $cellValue >= 0) {
            return (float)$cellValue;
        }
        $this->logger->warning("Invalid numeric value: '$cellValue' at row $row, column $column");
        return null;
    }

    private function createProductionFileRecord($filePath, ?UploadedFile $uploadedFile = null)
    {
         $user = $this->security->getUser();
    
    if (!$user) {
        $this->logger->error('No user is currently logged in, log in first :)');
        throw new \Exception('No user is currently logged in, log in first :)');
    }

        $productionFile = new ProductionFile();
        $productionFile->setFilename($uploadedFile ? $uploadedFile->getClientOriginalName() : basename($filePath));
        $productionFile->setImportedAt(new \DateTime());
        $productionFile->setUser($user);
        $productionFile->setValid(true);
        $productionFile->setDeleted(false);

        try {
            $this->entityManager->persist($productionFile);
            $this->entityManager->flush();
            $this->logger->info("Created ProductionFile: {$productionFile->getFilename()} (ID: {$productionFile->getId()})");
            return $productionFile->getId();
        } catch (\Exception $e) {
            $this->logger->error("Failed to create ProductionFile: {$e->getMessage()}");
            throw $e;
        }
    }

    private function getBras($brasName)
    {
        $bras = $this->entityManager->getRepository(Bras::class)->findOneBy([
            'nom' => $brasName,
            'valid' => true,
            'deleted' => false
        ]);
        if (!$bras) {
            $bras = new Bras();
            $bras->setNom($brasName);
            $bras->setValid(true);
            $bras->setDeleted(false);
            $this->entityManager->persist($bras);
            $this->entityManager->flush();
            $this->logger->info("Created new Bras: '$brasName' (ID: {$bras->getId()})");
        }
        return $bras;
    }

    private function insertShiftProduction($bras, $date, $posteType, $ref, $planifie, $realise, $cadenceHoraire, $objectifParPoste)
    {
        $shiftProduction = new ShiftProduction();
        $shiftProduction->setBras($bras);
        $shiftProduction->setProductionFile($this->entityManager->getReference(ProductionFile::class, $this->productionFileId));
        $shiftProduction->setDate(new \DateTime($date));
        $shiftProduction->setPosteType($posteType);
        $shiftProduction->setRef($ref);
        $shiftProduction->setObjectifParPoste($objectifParPoste ?: 0.0);
        $shiftProduction->setTargetParPoste($planifie ?: 0.0);
        $shiftProduction->setCadenceHoraire($cadenceHoraire ?: 0.0);
        $shiftProduction->setRealiseParPoste($realise ?: 0.0);
        $shiftProduction->setValid(true);
        $shiftProduction->setDeleted(false);

        try {
            $this->entityManager->persist($shiftProduction);
            $this->logger->info("Persisted ShiftProduction: Poste=$posteType, Ref=$ref, Date=$date");
        } catch (\Exception $e) {
            $this->logger->error("Failed to persist ShiftProduction: Poste=$posteType, Ref=$ref, Date=$date - {$e->getMessage()}");
            throw $e;
        }
    }
}