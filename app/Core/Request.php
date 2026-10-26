<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lee la petición actual: query string, cuerpo de formulario o JSON, y archivos.
 */
class Request
{
    /** @var array<string, mixed>|null */
    private $json;

    /** @var bool */
    private $jsonInvalido = false;

    /** El cuerpo decía ser JSON y no se pudo leer como objeto. */
    public function hasInvalidJson(): bool
    {
        $this->json();

        return $this->jsonInvalido;
    }

    /** Devuelve un valor de la query string. */
    public function query(string $key, $default = null)
    {
        return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
    }

    /** Devuelve un campo del formulario o, si el cuerpo es JSON, de ese JSON. */
    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        $json = $this->json();
        return array_key_exists($key, $json) ? $json[$key] : $default;
    }

    /**
     * Normaliza $_FILES a una lista, tanto si llegó un archivo como varios.
     *
     * @return array<int, array<string, mixed>>
     */
    public function files(string $key): array
    {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
            return [];
        }

        $file = $_FILES[$key];
        if (!is_array($file['name'])) {
            return [$file];
        }

        $lista = [];
        foreach ($file['name'] as $index => $name) {
            if ((int) $file['error'][$index] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $lista[] = [
                'name' => $name,
                'type' => $file['type'][$index],
                'tmp_name' => $file['tmp_name'][$index],
                'error' => $file['error'][$index],
                'size' => $file['size'][$index],
            ];
        }

        return $lista;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $tipo = isset($_SERVER['CONTENT_TYPE']) ? (string) $_SERVER['CONTENT_TYPE'] : '';
        if (stripos($tipo, 'application/json') === false) {
            $this->json = [];
            return $this->json;
        }

        $crudo = file_get_contents('php://input');
        $texto = $crudo === false ? '' : $crudo;
        if (trim($texto) === '') {
            $this->json = [];
            return $this->json;
        }

        $data = json_decode($texto, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $this->jsonInvalido = true;
            $this->json = [];
            return $this->json;
        }

        $this->json = $data;

        return $this->json;
    }
}
