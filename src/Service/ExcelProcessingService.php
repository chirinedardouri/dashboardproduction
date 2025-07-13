<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExcelProcessingService
{
    public function processProductionExcel(UploadedFile $file): array
    {
        // Load the Excel file
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Get all data as array
        $data = $worksheet->toArray();
        
        return $this->parseProductionData($data);
    }
    
    private function parseProductionData(array $rawData): array
    {
        $processedData = [];
        $dates = [];
        
        // Find date headers (look for dates in first row)
        $dateColumns = [];
        $headerRow = $rawData[0] ?? [];
        
        foreach ($headerRow as $colIndex => $cellValue) {
            if ($this->isDateString($cellValue)) {
                $dateStr = $this->formatDateString($cellValue);
                $dates[] = $dateStr;
                
                // For each date, we have P1, P2, P3 columns
                $dateColumns[] = [
                    'date' => $dateStr,
                    'p1_planifie' => $colIndex + 1,
                    'p1_realise' => $colIndex + 2,
                    'p2_planifie' => $colIndex + 4,
                    'p2_realise' => $colIndex + 5,
                    'p3_planifie' => $colIndex + 7,
                    'p3_realise' => $colIndex + 8
                ];
            }
        }
        
        // Process each BRAS row (skip header rows)
        for ($i = 2; $i < count($rawData); $i++) {
            $row = $rawData[$i];
            if (empty($row) || empty($row[1])) continue;
            
            $brasName = $row[1]; // Column B contains BRAS name
            $cadH = $row[2] ?? 0; // Column C contains Cad / H
            $objPoste = $row[3] ?? 0; // Column D contains Obj/ Poste
            
            if ($brasName && $brasName !== 'BRAS') {
                // Process each date column
                foreach ($dateColumns as $dateCol) {
                    $shifts = [
                        [
                            'poste' => 'P1',
                            'planifie' => $row[$dateCol['p1_planifie']] ?? 0,
                            'realise' => $row[$dateCol['p1_realise']] ?? 0
                        ],
                        [
                            'poste' => 'P2',
                            'planifie' => $row[$dateCol['p2_planifie']] ?? 0,
                            'realise' => $row[$dateCol['p2_realise']] ?? 0
                        ],
                        [
                            'poste' => 'P3',
                            'planifie' => $row[$dateCol['p3_planifie']] ?? 0,
                            'realise' => $row[$dateCol['p3_realise']] ?? 0
                        ]
                    ];
                    
                    // Create shiftProduction entries
                    foreach ($shifts as $shift) {
                        $processedData[] = [
                            'date' => $dateCol['date'],
                            'poste' => $shift['poste'],
                            'ref' => $row[$dateCol['p1_planifie'] - 1] ?? '',
                            'bras' => $brasName,
                            'objectifParPoste' => $objPoste,
                            'targetParPoste' => $shift['planifie'],
                            'cadenceHoraire' => $cadH,
                            'realiseParPoste' => $shift['realise']
                        ];
                    }
                }
            }
        }
        
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
            // Convert MM/DD/YYYY to YYYY-MM-DD
            $date = \DateTime::createFromFormat('m/d/Y', $value);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }
        
        return $value;
    }
    
    public function saveToDatabase(array $processedData, int $productionFileId): void
    {
        // This method would save the processed data to your ShiftProduction entity
        // You'll need to inject EntityManagerInterface and implement the save logic
    }
}