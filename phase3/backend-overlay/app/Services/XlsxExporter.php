<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class XlsxExporter
{
    /** Toutes les cellules sont écrites en texte pour conserver les zéros initiaux (CEF, CIN...). */
    public static function download(array $headers, iterable $rows, string $filename): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $write = function (array $row, int $r) use ($sheet) {
            foreach (array_values($row) as $c => $val) {
                if ($val === null || $val === '') {
                    continue;
                }
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c + 1).$r, (string) $val, DataType::TYPE_STRING);
            }
        };

        $write($headers, 1);
        $last = Coordinate::stringFromColumnIndex(max(1, count($headers)));
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);

        $r = 2;
        foreach ($rows as $row) {
            $write($row, $r++);
        }
        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'xl');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
