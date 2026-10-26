<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuestas HTTP: JSON o un archivo, para descargar o para verlo en el navegador.
 */
class Response
{
    /**
     * Envía JSON y corta el flujo del controlador que la llama.
     *
     * @param mixed $data
     */
    public static function json($data, int $code = 200): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $code = 500;
            $json = '{"mensaje":"No fue posible completar la operación."}';
        }

        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
    }

    /** Fuerza la descarga del archivo. */
    public static function download(string $path, string $name): void
    {
        self::sendFile($path, $name, 'application/octet-stream', 'attachment');
    }

    /** Muestra el archivo en el navegador, sin forzar la descarga. */
    public static function inline(string $path, string $name, string $mime): void
    {
        self::sendFile($path, $name, $mime, 'inline');
    }

    private static function sendFile(string $path, string $name, string $mime, string $disposition): void
    {
        $safe = self::safeName($name);
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $safe);
        if (!is_string($ascii) || $ascii === '') {
            $ascii = 'documento';
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($safe));
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    private static function safeName(string $name): string
    {
        $limpio = str_replace(['"', "\r", "\n", '\\', '/'], '', $name);
        if ($limpio === '') {
            return 'documento';
        }

        return $limpio;
    }
}
