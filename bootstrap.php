<?php

declare(strict_types=1);

/**
 * Arranque común: zona horaria, autoload, .env, Eloquent y el registro de errores.
 * Un fallo no controlado responde JSON en /api y texto plano en el resto.
 */

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Response;

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('America/Bogota');

require __DIR__ . '/vendor/autoload.php';

/**
 * Responde un fallo sin mostrar la excepción. En /api el cuerpo es JSON;
 * en el resto, un texto para quien abre la página.
 */
function failRequest(Throwable $e): void
{
    Logger::error($e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (headers_sent()) {
        return;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if (strpos($uri, '/api/') === 0) {
        Response::json(['mensaje' => 'No fue posible completar la operación.'], 500);
        return;
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No fue posible iniciar la aplicación. Revisa el archivo .env y que MySQL esté en ejecución.';
}

set_exception_handler(static function (Throwable $e): void {
    failRequest($e);
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatales, true)) {
        return;
    }

    failRequest(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
});

Env::load(__DIR__ . '/.env');
Database::boot();
