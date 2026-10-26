<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Oferta. El modelo consulta la base a través de Eloquent suelto, no con SQL directo.
 * Fecha y hora van en columnas separadas porque así está definida la tabla.
 * El estado se guarda y se vuelve a calcular al listar: el formulario no lo envía,
 * y una oferta creada como activa pasa sola a publicada o a evaluación.
 * creado_en y actualizado_en los mantiene Eloquent.
 */
class Offer extends Model
{
    /**
     * Columnas del listado y del Excel. El detalle pide la fila completa.
     *
     * @var array<int, string>
     */
    private const LISTADO = [
        'id',
        'consecutivo',
        'objeto',
        'descripcion',
        'fecha_inicio',
        'fecha_cierre',
        'estado',
    ];

    protected $table = 'ofertas';

    protected $guarded = ['id'];

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'actualizado_en';

    /** Actividad UNSPSC elegida para la oferta. */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'actividad_id');
    }

    /** Documentos cargados en la edición. */
    public function documents(): HasMany
    {
        return $this->hasMany(OfferDocument::class, 'licitacion_id');
    }

    /**
     * Deja en cada oferta el estado que corresponde al instante recibido.
     */
    public static function syncStates(string $now): void
    {
        [$date, $time] = self::splitMoment($now);

        static::query()
            ->where(function ($query) use ($date, $time) {
                self::whereMoment($query, 'fecha_cierre', 'hora_cierre', '<=', $date, $time);
            })
            ->where('estado', '<>', 'en evaluacion')
            ->update([
                'estado' => 'en evaluacion',
                'actualizado_en' => $now,
            ]);

        static::query()
            ->where(function ($query) use ($date, $time) {
                self::whereMoment($query, 'fecha_cierre', 'hora_cierre', '>', $date, $time);
            })
            ->where(function ($query) use ($date, $time) {
                self::whereMoment($query, 'fecha_inicio', 'hora_inicio', '<=', $date, $time);
            })
            ->where('estado', '<>', 'publicada')
            ->update([
                'estado' => 'publicada',
                'actualizado_en' => $now,
            ]);

        static::query()
            ->where(function ($query) use ($date, $time) {
                self::whereMoment($query, 'fecha_inicio', 'hora_inicio', '>', $date, $time);
            })
            ->where('estado', '<>', 'activo')
            ->update([
                'estado' => 'activo',
                'actualizado_en' => $now,
            ]);
    }

    /**
     * Página del listado. El total cuenta el filtro completo, no solo la página.
     *
     * @param array<string, string> $filters
     * @return array{total: int, filas: Collection<int, Offer>}
     */
    public static function listPage(array $filters, int $page, int $perPage): array
    {
        $query = self::filter($filters)->select(self::LISTADO);
        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('creado_en')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return ['total' => $total, 'filas' => $rows];
    }

    /**
     * Todas las ofertas del filtro, sin paginar, para el Excel.
     *
     * @param array<string, string> $filters
     * @return Collection<int, Offer>
     */
    public static function listForExcel(array $filters): Collection
    {
        return self::filter($filters)
            ->select(self::LISTADO)
            ->orderByDesc('creado_en')
            ->orderByDesc('id')
            ->get();
    }

    /** Oferta con su actividad y sus documentos, o null si no existe. */
    public static function findWithDetails(int $id): ?self
    {
        return static::query()->with(['activity', 'documents'])->find($id);
    }

    /**
     * Debe llamarse dentro de una transacción para que el bloqueo sea efectivo.
     */
    public static function lastConsecutiveForUpdate(): ?string
    {
        $value = static::query()->orderByDesc('id')->lockForUpdate()->value('consecutivo');
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param array<string, string> $filters
     */
    private static function filter(array $filters): Builder
    {
        $query = static::query();

        foreach (['consecutivo', 'objeto', 'descripcion'] as $campo) {
            $value = trim((string) ($filters[$campo] ?? ''));
            if ($value === '') {
                continue;
            }
            // El consecutivo completo usa el índice único. Un fragmento, el objeto
            // y la descripción se buscan dentro del texto: así "0001" sigue
            // encontrando PO-0001-26 y una palabra suelta sigue encontrando la oferta.
            if ($campo === 'consecutivo' && preg_match('/^PO-\d{4}-\d{2}$/i', $value) === 1) {
                $query->where($campo, $value);
                continue;
            }
            $query->where($campo, 'like', '%' . self::escapeLike($value) . '%');
        }

        return $query;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Compara fecha y hora por columnas, equivalente a comparar el instante completo.
     * Así el índice de (fecha, hora) sirve y el resultado no cambia.
     */
    private static function whereMoment(
        Builder $query,
        string $dateColumn,
        string $timeColumn,
        string $operator,
        string $date,
        string $time
    ): void {
        $allowed = ['fecha_inicio', 'hora_inicio', 'fecha_cierre', 'hora_cierre'];
        if (!in_array($dateColumn, $allowed, true) || !in_array($timeColumn, $allowed, true)) {
            throw new \InvalidArgumentException('Columna de cronograma no permitida.');
        }
        if (!in_array($operator, ['<=', '>'], true)) {
            throw new \InvalidArgumentException('Comparación de cronograma no permitida.');
        }

        $dateOperator = $operator === '<=' ? '<' : '>';
        $query->where($dateColumn, $dateOperator, $date)
            ->orWhere(function ($query) use ($dateColumn, $timeColumn, $operator, $date, $time) {
                $query->where($dateColumn, '=', $date)
                    ->where($timeColumn, $operator, $time);
            });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitMoment(string $now): array
    {
        $parts = explode(' ', $now, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('La fecha de referencia no es válida.');
        }

        return [$parts[0], $parts[1]];
    }
}
