<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Enrutador propio. Cada ruta es método, patrón con {param} y un callable.
 * El primero que coincide atiende la petición.
 */
class Router
{
    /** @var array<int, array{0: string, 1: string, 2: callable}> */
    private $paths = [];

    /** Registra una ruta GET. */
    public function get(string $pattern, callable $action): void
    {
        $this->paths[] = ['GET', $pattern, $action];
    }

    /** Registra una ruta POST. */
    public function post(string $pattern, callable $action): void
    {
        $this->paths[] = ['POST', $pattern, $action];
    }

    /** Busca la ruta y la ejecuta. Si ninguna coincide, responde 404. */
    public function dispatch(string $method, string $uri): void
    {
        $path = rtrim($uri, '/') ?: '/';
        $method = strtoupper($method);

        foreach ($this->paths as $definition) {
            if ($definition[0] !== $method) {
                continue;
            }

            $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $definition[1]);
            if (!is_string($regex) || !preg_match('#^' . $regex . '$#', $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            call_user_func($definition[2], $params);
            return;
        }

        Response::json(['mensaje' => 'No encontrado.'], 404);
    }
}
