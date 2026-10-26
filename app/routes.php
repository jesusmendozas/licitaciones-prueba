<?php

declare(strict_types=1);

use App\Controllers\ActivityController;
use App\Controllers\OfferController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Export\OfferExporter;
use App\Services\DocumentStorage;
use App\Support\Clock;

/**
 * Registra las rutas de la API. El orden importa: /excel y /documents
 * van antes que /offers/{id} para que el enrutador no las confunda.
 */
function registerRoutes(Router $router): void
{
    $request = new Request();
    $clock = new Clock();
    $offers = new OfferController(new DocumentStorage(), new OfferExporter(), $clock);
    $activity = new ActivityController();

    $router->get('/api/activities', static function () use ($activity, $request): void {
        $activity->search($request);
    });

    $router->get('/api/offers/excel', static function () use ($offers, $request): void {
        $offers->excel($request);
    });

    $router->get('/api/offers', static function () use ($offers, $request): void {
        $offers->list($request);
    });

    $router->post('/api/offers', static function () use ($offers, $request): void {
        $offers->create($request);
    });

    $router->get('/api/offers/{id}/documents/{document}/download', static function (array $params) use ($offers): void {
        $id = routeId($params, 'id');
        $document = routeId($params, 'document');
        if ($id === null || $document === null) {
            Response::json(['mensaje' => 'No encontrado.'], 404);
            return;
        }
        $offers->download($id, $document);
    });

    $router->get('/api/offers/{id}/documents/{document}/{name}', static function (array $params) use ($offers): void {
        $id = routeId($params, 'id');
        $document = routeId($params, 'document');
        if ($id === null || $document === null || ($params['name'] ?? '') === 'download') {
            Response::json(['mensaje' => 'No encontrado.'], 404);
            return;
        }
        $offers->view($id, $document);
    });

    $router->get('/api/offers/{id}', static function (array $params) use ($offers): void {
        $id = routeId($params, 'id');
        if ($id === null) {
            Response::json(['mensaje' => 'No encontrado.'], 404);
            return;
        }
        $offers->detail($id);
    });

    $router->post('/api/offers/{id}', static function (array $params) use ($offers, $request): void {
        $id = routeId($params, 'id');
        if ($id === null) {
            Response::json(['mensaje' => 'No encontrado.'], 404);
            return;
        }
        $offers->update($request, $id);
    });
}

/**
 * Lee un parámetro de ruta y lo acepta solo si es un entero positivo.
 *
 * @param array<string, string> $params
 */
function routeId(array $params, string $key): ?int
{
    if (!isset($params[$key]) || !ctype_digit($params[$key])) {
        return null;
    }

    return (int) $params[$key];
}
