<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ShiftProduction;
use App\Entity\Bras;
use App\Entity\ProductionFile;

class ExcelProcessingService
{
    private EntityManagerInterface $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    public function importProductionData(string $filePath): array
    {
        $importedCount = 0;
        $errors = [];
        
        try {
            // Load the Excel file
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get all data as array
            $data = $worksheet->toArray();
            
            // Debug: Log the first few rows of raw data
            error_log('Raw Excel data (first 5 rows):');
            for ($i = 0; $i < min(5, count($data)); $i++) {
                error_log('Row ' . $i . ': ' . print_r($data[$i], true));
            }
            
            // Parse the data
            $parsedResult = $this->parseProductionData($data);
            
            // Debug: Log parsed result
            error_log('Parsed result count: ' . count($parsedResult['data']));
            error_log('Parsed dates: ' . print_r($parsedResult['dates'], true));
            
            if (empty($parsedResult['data'])) {
                $errors[] = 'No valid data found in Excel file';
                error_log('No valid data found - raw data structure: ' . print_r($data, true));
                return ['imported' => 0, 'errors' => $errors];
            }
            
            // Create or find ProductionFile entity
            $productionFile = new ProductionFile();
            $productionFile->setFileName('imported_' . date('Y-m-d_H-i-s') . '.xlsx');
            $productionFile->setUploadedAt(new \DateTime());
            $this->entityManager->persist($productionFile);
            $this->entityManager->flush(); // Flush to get the ID
            
            // Save each processed data entry
            foreach ($parsedResult['data'] as $dataEntry) {
                try {
                    error_log('Processing entry: ' . print_r($dataEntry, true));
                    
                    // Find the Bras entity
                    $bras = $this->entityManager->getRepository(Bras::class)
                        ->findOneBy(['name' => $dataEntry['bras']]);
                    
                    if (!$bras) {
                        $errors[] = "BRAS '{$dataEntry['bras']}' not found in database";
                        error_log("BRAS '{$dataEntry['bras']}' not found in database");
                        continue;
                    }
                    
                    // Create ShiftProduction entity
                    $shiftProduction = new ShiftProduction();
                    $shiftProduction->setDate(new \DateTime($dataEntry['date']));
                    $shiftProduction->setPosteType($dataEntry['poste']);
                    $shiftProduction->setRef($dataEntry['ref']);
                    $shiftProduction->setBras($bras);
                    $shiftProduction->setObjectifParPoste($dataEntry['objectifParPoste']);
                    $shiftProduction->setTargetParPoste($dataEntry['targetParPoste']);
                    $shiftProduction->setCadenceHoraire($dataEntry['cadenceHoraire']);
                    $shiftProduction->setRealiseParPoste($dataEntry['realiseParPoste']);
                    $shiftProduction->setProductionFile($productionFile);
                    
                    $this->entityManager->persist($shiftProduction);
                    $importedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Error saving data for {$dataEntry['bras']} on {$dataEntry['date']}: " . $e->getMessage();
                    error_log("Error saving data: " . $e->getMessage());
                }
            }
            
            // Save all entities
            $this->entityManager->flush();
            
        } catch (\Exception $e) {
            $errors[] = "Error processing Excel file: " . $e->getMessage();
            error_log("Error processing Excel file: " . $e->getMessage());
        }
        
        return [
            'imported' => $importedCount,
            'errors' => $errors
        ];
    }
    
    public function importScheduleFromExcel(string $filePath): array
    {
        $importedCount = 0;
        $errors = [];
        
        try {
            // Implement your schedule import logic here
            // For now, return empty result
            $errors[] = "Schedule import not yet implemented";
            
        } catch (\Exception $e) {
            $errors[] = "Error importing schedule: " . $e->getMessage();
        }
        
        return [
            'imported' => $importedCount,
            'errors' => $errors
        ];
    }
    
    private function parseProductionData(array $rawData): array
    {
        $processedData = [];
        $dates = [];
        
        error_log('Starting parseProductionData with ' . count($rawData) . ' rows');
        
        // Find date headers (look for dates in first row)
        $dateColumns = [];
        $headerRow = $rawData[0] ?? [];
        
        error_log('Header row: ' . print_r($headerRow, true));
        
        foreach ($headerRow as $colIndex => $cellValue) {
            error_log("Checking column $colIndex: '$cellValue'");
            
            if ($this->isDateString($cellValue)) {
                $dateStr = $this->formatDateString($cellValue);
                $dates[] = $dateStr;
                
                error_log("Found date: $dateStr at column $colIndex");
                
                // For each date, we have REF, P1, P2, P3 columns
                // Based on your Excel format, adjust these column indices
                $dateColumns[] = [
                    'date' => $dateStr,
                    'ref_col' => $colIndex,
                    'p1_planifie' => $colIndex + 1,
                    'p1_realise' => $colIndex + 2,
                    'p2_planifie' => $colIndex + 4,
                    'p2_realise' => $colIndex + 5,
                    'p3_planifie' => $colIndex + 7,
                    'p3_realise' => $colIndex + 8
                ];
            }
        }
        
        error_log('Found date columns: ' . print_r($dateColumns, true));
        
        // If no date columns found, try to find them in other rows
        if (empty($dateColumns)) {
            error_log('No dates found in first row, checking other rows...');
            
            // Check first few rows for date patterns
            for ($rowIndex = 0; $rowIndex < min(5, count($rawData)); $rowIndex++) {
                $row = $rawData[$rowIndex];
                foreach ($row as $colIndex => $cellValue) {
                    if ($this->isDateString($cellValue)) {
                        error_log("Found date '$cellValue' in row $rowIndex, column $colIndex");
                    }
                }
            }
        }
        
        // Process each BRAS row (skip header rows)
        for ($i = 2; $i < count($rawData); $i++) {
            $row = $rawData[$i];
            if (empty($row) || empty($row[1])) continue;
            
            $brasName = $row[1]; // Column B contains BRAS name
            $cadH = floatval($row[2] ?? 0); // Column C contains Cad / H
            $objPoste = floatval($row[3] ?? 0); // Column D contains Obj/ Poste
            
            error_log("Processing row $i: BRAS='$brasName', CadH=$cadH, ObjPoste=$objPoste");
            
            if ($brasName && $brasName !== 'BRAS') {
                // Process each date column
                foreach ($dateColumns as $dateCol) {
                    $shifts = [
                        [
                            'poste' => 'P1',
                            'planifie' => floatval($row[$dateCol['p1_planifie']] ?? 0),
                            'realise' => floatval($row[$dateCol['p1_realise']] ?? 0)
                        ],
                        [
                            'poste' => 'P2',
                            'planifie' => floatval($row[$dateCol['p2_planifie']] ?? 0),
                            'realise' => floatval($row[$dateCol['p2_realise']] ?? 0)
                        ],
                        [
                            'poste' => 'P3',
                            'planifie' => floatval($row[$dateCol['p3_planifie']] ?? 0),
                            'realise' => floatval($row[$dateCol['p3_realise']] ?? 0)
                        ]
                    ];
                    
                    // Create shiftProduction entries
                    foreach ($shifts as $shift) {
                        $entry = [
                            'date' => $dateCol['date'],
                            'poste' => $shift['poste'],
                            'ref' => $row[$dateCol['ref_col']] ?? '',
                            'bras' => $brasName,
                            'objectifParPoste' => $objPoste,
                            'targetParPoste' => $shift['planifie'],
                            'cadenceHoraire' => $cadH,
                            'realiseParPoste' => $shift['realise']
                        ];
                        
                        error_log('Created entry: ' . print_r($entry, true));
                        $processedData[] = $entry;
                    }
                }
            }
        }
        
        error_log('Final processed data count: ' . count($processedData));
        
        return [
            'data' => $processedData,
            'dates' => array_unique($dates)
        ];
    }
    
    private function isDateString($value): bool
    {
        if (empty($value)) return false;
        
        // Check if it's a date string (MM/DD/YYYY format)
        if (is_string($value) && preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
            return true;
        }
        
        // Check if it's a numeric date (Excel serial date)
        if (is_numeric($value) && Date::isDateTime($value)) {
            return true;
        }
        
        // Check for other date formats
        if (is_string($value)) {
            // Try to parse as date
            $date = \DateTime::createFromFormat('m/d/Y', $value);
            if ($date !== false) return true;
            
            $date = \DateTime::createFromFormat('d/m/Y', $value);
            if ($date !== false) return true;
            
            $date = \DateTime::createFromFormat('Y-m-d', $value);
            if ($date !== false) return true;
        }
        
        return false;
    }
    
    private function formatDateString($value): string
    {
        if (is_numeric($value)) {
            // Convert Excel serial date to PHP date
            $date = Date::excelToDateTimeObject($value);
            return $date->format('Y-m-d');
        }
        
        if (is_string($value)) {
            // Try different date formats
            $formats = ['m/d/Y', 'd/m/Y', 'Y-m-d', 'n/j/Y'];
            
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            }
        }
        
        return $value;
    }
}