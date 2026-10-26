<?php

declare(strict_types=1);

namespace App\Export;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera el Excel del listado en el servidor y lo envía como descarga.
 */
class OfferExporter
{
    /**
     * Escribe el libro en la salida HTTP. Las celdas van como texto para no alterar consecutivos.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function download(array $rows): void
    {
        $libro = new Spreadsheet();
        $sheet = $libro->getActiveSheet();
        $sheet->setTitle('Ofertas');

        $encabezados = ['Consecutivo', 'Objeto', 'Descripción', 'Fecha de inicio', 'Fecha de cierre', 'Estado'];
        $columnas = ['consecutivo', 'objeto', 'descripcion', 'fecha_inicio', 'fecha_cierre', 'estado'];

        foreach ($encabezados as $index => $encabezado) {
            $sheet->setCellValueExplicit([$index + 1, 1], $encabezado, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        $numeroFila = 2;
        foreach ($rows as $row) {
            foreach ($columnas as $index => $column) {
                $value = isset($row[$column]) ? (string) $row[$column] : '';
                $sheet->setCellValueExplicit([$index + 1, $numeroFila], $value, DataType::TYPE_STRING);
            }
            $numeroFila++;
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="ofertas.xlsx"');
        header('Cache-Control: max-age=0');
        header('X-Content-Type-Options: nosniff');

        $escritor = new Xlsx($libro);
        $escritor->save('php://output');
        $libro->disconnectWorksheets();
    }
}
