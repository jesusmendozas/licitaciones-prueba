<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Carga el .env local. No interpreta comillas: cada valor es el texto después del primer =.
 */
class Env
{
    /** Lee el archivo y publica cada variable en el entorno del proceso. */
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('Falta el archivo .env. Copia .env.example y completa la contraseña.');
        }

        $lineas = file($path, FILE_IGNORE_NEW_LINES);
        if ($lineas === false) {
            throw new RuntimeException('No se pudo leer el archivo .env.');
        }

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#') {
                continue;
            }

            $parts = explode('=', $linea, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    /** Devuelve una variable ya cargada, o el valor por defecto si no existe. */
    public static function get(string $key, string $default = ''): string
    {
        if (isset($_ENV[$key])) {
            return (string) $_ENV[$key];
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return (string) $value;
    }
}
