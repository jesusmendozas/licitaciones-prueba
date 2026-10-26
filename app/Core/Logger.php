<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Escribe los errores no controlados en storage/logs/app.log.
 * La respuesta al cliente no incluye el detalle de la excepción.
 */
class Logger
{
    /** Agrega una línea con el mensaje, el archivo y la línea del error. */
    public static function error(Throwable $e): void
    {
        $directorio = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }

        $linea = date('Y-m-d H:i:s')
            . ' '
            . $e->getMessage()
            . ' en '
            . $e->getFile()
            . ':'
            . $e->getLine()
            . PHP_EOL;

        file_put_contents($directorio . DIRECTORY_SEPARATOR . 'app.log', $linea, FILE_APPEND);
    }
}
