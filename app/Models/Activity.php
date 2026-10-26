<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo UNSPSC. El Excel se lee una sola vez al cargar la maestra:
 * en cada búsqueda ir al archivo repetiría 49.000 filas. La consulta
 * usa el inicio del texto y un límite de 20 para apoyarse en el índice.
 */
class Activity extends Model
{
    protected $table = 'actividades';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * Sin texto devuelve las primeras 20 actividades. Con texto, busca por el
     * inicio del nombre o del código y también devuelve como máximo 20 filas.
     * El nombre usa el índice de producto. El código, que es numérico, se
     * acota por rangos para usar el índice en lugar de convertir la columna.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $term): array
    {
        $columns = ['id', 'codigo_producto', 'producto', 'familia'];

        if ($term === '') {
            return static::query()
                ->select($columns)
                ->orderBy('producto')
                ->limit(20)
                ->get()
                ->toArray();
        }

        $byName = static::query()
            ->select($columns)
            ->where('producto', 'like', self::escapeLike($term) . '%')
            ->orderBy('producto')
            ->limit(20);

        $ranges = self::codePrefixRanges($term);
        if ($ranges === []) {
            return $byName->get()->toArray();
        }

        $byCode = static::query()
            ->select($columns)
            ->where(function ($query) use ($ranges) {
                foreach ($ranges as $index => $range) {
                    $method = $index === 0 ? 'whereBetween' : 'orWhereBetween';
                    $query->{$method}('codigo_producto', $range);
                }
            })
            ->orderBy('producto')
            ->limit(20);

        return $byName
            ->union($byCode)
            ->orderByRaw('producto')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /** Confirma que la actividad elegida en el formulario existe. */
    public static function existsWithId(int $id): bool
    {
        return static::query()->where('id', $id)->exists();
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Rangos de enteros cuya representación decimal empieza por el texto.
     * Así el prefijo del código usa el índice y coincide con un LIKE sobre el número.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private static function codePrefixRanges(string $term): array
    {
        if ($term === '' || !ctype_digit($term) || strlen($term) > 10) {
            return [];
        }

        if ($term[0] === '0') {
            return $term === '0' ? [[0, 0]] : [];
        }

        $ranges = [];
        $max = '2147483647';
        $length = strlen($term);
        for ($digits = $length; $digits <= 10; $digits++) {
            $from = $term . str_repeat('0', $digits - $length);
            $to = $term . str_repeat('9', $digits - $length);
            if (self::aboveIntegerLimit($from, $max)) {
                break;
            }
            if (self::aboveIntegerLimit($to, $max)) {
                $to = $max;
            }
            $ranges[] = [(int) $from, (int) $to];
        }

        return $ranges;
    }

    /** Compara el número como texto, sin tratar "99" como mayor que "2147483647". */
    private static function aboveIntegerLimit(string $number, string $max): bool
    {
        if (strlen($number) !== strlen($max)) {
            return strlen($number) > strlen($max);
        }

        return strcmp($number, $max) > 0;
    }
}
