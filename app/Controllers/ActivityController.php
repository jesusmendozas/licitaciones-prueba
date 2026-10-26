<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Activity;

/**
 * Búsqueda de actividades para el formulario. Responde como máximo 20 filas.
 */
class ActivityController
{
    /**
     * Busca por el inicio del nombre o del código. Con menos de 2 caracteres
     * no consulta y devuelve una lista vacía.
     */
    public function search(Request $request): void
    {
        $raw = $request->query('q', '');
        if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
            Response::json(['errores' => ['q' => 'El texto de búsqueda no es válido.']], 422);
            return;
        }

        $term = trim((string) $raw);

        if ($term !== '' && mb_strlen($term) < 2) {
            Response::json(['data' => []]);
            return;
        }

        if (mb_strlen($term) > 100) {
            Response::json(['errores' => ['q' => 'El texto de búsqueda es demasiado largo.']], 422);
            return;
        }

        Response::json(['data' => Activity::search($term)]);
    }
}
