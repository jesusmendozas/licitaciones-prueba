<?php

declare(strict_types=1);

/**
 * Carga la maestra UNSPSC desde el Excel. Si el producto ya existe, actualiza
 * sus nombres y conserva el id que usan las ofertas. No borra una actividad
 * que una oferta esté usando.
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use PhpOffice\PhpSpreadsheet\IOFactory;

require dirname(__DIR__) . '/bootstrap.php';

ini_set('memory_limit', '512M');

$path = $argv[1] ?? __DIR__ . DIRECTORY_SEPARATOR . 'maestra-actividades.xlsx';
if (!is_file($path)) {
    fwrite(STDERR, "No se encontró el Excel de actividades: {$path}\n");
    exit(1);
}

$reader = IOFactory::createReader('Xlsx');
$reader->setReadDataOnly(true);
$sheet = $reader->load($path)->getActiveSheet();
$headerRow = headerRow($sheet);
$rows = activityRows($sheet, $headerRow + 1);

Capsule::connection()->transaction(static function () use ($rows): void {
    $incoming = [];
    foreach ($rows as $row) {
        $incoming[$row['codigo_producto']] = $row;
    }

    $current = [];
    foreach (Capsule::connection()->table('actividades')->get() as $activity) {
        $current[(int) $activity->codigo_producto] = $activity;
    }

    $inserts = [];
    foreach ($incoming as $code => $row) {
        if (!isset($current[$code])) {
            $inserts[] = $row;
            continue;
        }

        $activity = $current[$code];
        $changes = [];
        foreach (['codigo_segmento', 'segmento', 'codigo_familia', 'familia', 'codigo_clase', 'clase', 'producto'] as $field) {
            if ((string) $activity->{$field} !== (string) $row[$field]) {
                $changes[$field] = $row[$field];
            }
        }
        if ($changes !== []) {
            Capsule::connection()->table('actividades')->where('id', $activity->id)->update($changes);
        }
    }

    foreach (array_chunk($inserts, 400) as $batch) {
        Capsule::connection()->table('actividades')->insert($batch);
    }

    $removed = [];
    foreach ($current as $code => $activity) {
        if (!isset($incoming[$code])) {
            $removed[] = (int) $activity->id;
        }
    }
    if ($removed === []) {
        return;
    }

    $used = Capsule::connection()->table('ofertas')->whereIn('actividad_id', $removed)->exists();
    if ($used) {
        throw new RuntimeException('El Excel ya no trae actividades que están usadas por ofertas. No se modifica la maestra.');
    }

    foreach (array_chunk($removed, 400) as $batch) {
        Capsule::connection()->table('actividades')->whereIn('id', $batch)->delete();
    }
});

$saved = (int) Capsule::connection()->table('actividades')->count();
echo "Actividades cargadas desde el Excel: {$saved}\n";

function headerRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): int
{
    $limit = min(20, $sheet->getHighestRow());
    for ($row = 1; $row <= $limit; $row++) {
        $texto = cellText($sheet, 7, $row);
        if (mb_strtolower($texto) === 'código producto') {
            return $row;
        }
    }

    throw new RuntimeException('El Excel no tiene la columna Código Producto.');
}

/**
 * @return array<int, array<string, int|string>>
 */
function activityRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $from): array
{
    $rows = [];
    $seen = [];
    $lastRow = $sheet->getHighestRow();
    for ($number = $from; $number <= $lastRow; $number++) {
        $code = cellText($sheet, 7, $number);
        $producto = cellText($sheet, 8, $number);
        if ($code === '' || $producto === '') {
            continue;
        }
        if (!ctype_digit($code)) {
            throw new RuntimeException("El código de producto de la fila {$number} no es numérico.");
        }
        if (isset($seen[(int) $code])) {
            throw new RuntimeException("El código de producto {$code} está repetido en el Excel.");
        }
        $seen[(int) $code] = true;

        $rows[] = [
            'codigo_segmento' => cellCode($sheet, 1, $number),
            'segmento' => cellText($sheet, 2, $number),
            'codigo_familia' => cellCode($sheet, 3, $number),
            'familia' => cellText($sheet, 4, $number),
            'codigo_clase' => cellCode($sheet, 5, $number),
            'clase' => cellText($sheet, 6, $number),
            'codigo_producto' => (int) $code,
            'producto' => $producto,
        ];
    }

    if ($rows === []) {
        throw new RuntimeException('El Excel no tiene productos para cargar.');
    }

    return $rows;
}

function cellText(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $column, int $row): string
{
    $value = $sheet->getCell([$column, $row])->getValue();
    if ($value === null) {
        return '';
    }
    if (is_float($value) || is_int($value)) {
        return (string) (int) $value;
    }

    return trim((string) $value);
}

function cellCode(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $column, int $row): int
{
    $value = cellText($sheet, $column, $row);
    if ($value === '' || !ctype_digit($value)) {
        throw new RuntimeException("Hay un código vacío o no numérico en la fila {$row}.");
    }

    return (int) $value;
}
