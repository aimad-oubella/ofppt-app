<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetReader
{
    /**
     * Lit une feuille d'un fichier .xls ou .xlsx.
     *
     * @return array{sheets: string[], rows: array<int, array<int, mixed>>}
     */
    public function read(string $path, int $sheetIndex = 0): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $names = $spreadsheet->getSheetNames();
        if (! isset($names[$sheetIndex])) {
            $spreadsheet->disconnectWorksheets();
            throw new \RuntimeException("La feuille n°".($sheetIndex + 1)." n'existe pas dans ce fichier.");
        }

        // Valeurs brutes (dates = numéros de série), sans mise en forme
        $rows = $spreadsheet->getSheet($sheetIndex)->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        return ['sheets' => $names, 'rows' => $rows];
    }
}
