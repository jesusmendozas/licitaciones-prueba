<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento de una oferta. El archivo vive en storage/uploads; aquí se guarda la ruta.
 */
class OfferDocument extends Model
{
    protected $table = 'ofertas_documentos';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** Oferta dueña del documento. */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'licitacion_id');
    }

    /** Registra un documento ya guardado en disco. */
    public static function createFor(int $offerId, string $title, string $description, string $path): self
    {
        return static::query()->create([
            'licitacion_id' => $offerId,
            'titulo' => $title,
            'descripcion' => $description,
            'archivo' => $path,
            'creado_en' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Cuántos documentos tiene ya la oferta. La edición exige al menos uno. */
    public static function countForOffer(int $offerId): int
    {
        return static::query()->where('licitacion_id', $offerId)->count();
    }

    /** Busca el documento solo si pertenece a esa oferta. */
    public static function findForOffer(int $offerId, int $documentId): ?self
    {
        return static::query()
            ->where('licitacion_id', $offerId)
            ->where('id', $documentId)
            ->first();
    }
}
