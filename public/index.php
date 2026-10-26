<?php

declare(strict_types=1);

/**
 * Punto de entrada. Los archivos estáticos los sirve el servidor;
 * el resto de la web entrega index.html y /api entra al enrutador.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($uri) || $uri === '') {
    $uri = '/';
}

if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . $uri;
    if ($uri !== '/' && is_file($archivo)) {
        return false;
    }
}

require __DIR__ . '/../bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

if (strpos($uri, '/api/') !== 0) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    return;
}

require __DIR__ . '/../app/routes.php';

$router = new App\Core\Router();
registerRoutes($router);
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $uri);
