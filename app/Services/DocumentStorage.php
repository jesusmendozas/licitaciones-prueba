<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

/**
 * Guarda los archivos fuera de public para que no se puedan pedir por URL directa.
 * El nombre en disco se cifra: la ruta no debe revelar el título que escribió el usuario.
 * La extensión se comprueba con la firma del archivo, porque renombrar un .exe a .pdf
 * no debe bastar para aceptarlo.
 */
class DocumentStorage
{
    public const TAMANO_MAXIMO = 10485760;

    /**
     * Acepta solo PDF o ZIP de hasta 10 MB y comprueba la firma del archivo, no solo la extensión.
     * Devuelve el mensaje de error, o null si el archivo sirve.
     *
     * @param array<string, mixed> $file
     */
    public function validateFile(array $file): ?string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return 'Cada archivo debe pesar entre 1 byte y 10 MB.';
        }
        if ($error !== UPLOAD_ERR_OK) {
            return 'No se pudo cargar el archivo.';
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::TAMANO_MAXIMO) {
            return 'Cada archivo debe pesar entre 1 byte y 10 MB.';
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'zip'], true)) {
            return 'Solo se permiten archivos PDF o ZIP.';
        }

        $temporary = (string) $file['tmp_name'];
        if (!is_uploaded_file($temporary)) {
            return 'No se pudo cargar el archivo.';
        }

        $header = file_get_contents($temporary, false, null, 0, 1024);
        $isPdf = $extension === 'pdf' && is_string($header) && strpos($header, '%PDF') !== false;
        $isZip = $extension === 'zip' && is_string($header) && strncmp($header, 'PK', 2) === 0;
        if (!$isPdf && !$isZip) {
            return 'Solo se permiten archivos PDF o ZIP.';
        }

        return null;
    }

    /**
     * Mueve los archivos a storage/uploads. Si uno falla, borra los que ya había copiado.
     *
     * @param array<int, array<string, mixed>> $files
     * @return array<int, string>
     */
    public function store(array $files): array
    {
        $folder = $this->folder();
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new RuntimeException('No se pudo preparar la carpeta de documentos.');
        }

        $paths = [];
        foreach ($files as $file) {
            $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $name = $this->protectedFileName((string) $file['name'], $extension);
            $destination = $folder . DIRECTORY_SEPARATOR . $name;
            if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
                $this->delete($paths);
                throw new RuntimeException('No se pudo guardar el archivo.');
            }
            $paths[] = 'storage/uploads/' . $name;
        }

        return $paths;
    }

    /**
     * Borra archivos ya copiados cuando el guardado de la oferta no se completa.
     *
     * @param array<int, string> $paths
     */
    public function delete(array $paths): void
    {
        foreach ($paths as $path) {
            $absolute = $this->absolutePath($path);
            if ($this->isInsideUploads($absolute) && is_file($absolute)) {
                unlink($absolute);
            }
        }
    }

    /** Convierte la ruta guardada en la base en una ruta absoluta del disco. */
    public function absolutePath(string $relative): string
    {
        $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative);

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relative;
    }

    /** Impide leer un archivo que no esté dentro de storage/uploads. */
    public function isInsideUploads(string $absolute): bool
    {
        $base = realpath($this->folder());
        $real = realpath($absolute);
        if ($base === false || $real === false) {
            return false;
        }

        return strpos($real, $base . DIRECTORY_SEPARATOR) === 0;
    }

    /** Recupera el nombre original para mostrarlo y para la descarga. */
    public function visibleName(string $path): string
    {
        $base = basename(str_replace('\\', '/', $path));
        if (preg_match('/^(?:v1\.)?([A-Za-z0-9_-]+)\.(pdf|zip)$/', $base, $matches) === 1) {
            $plain = $this->decryptName($matches[1]);
            if (is_string($plain) && $this->nameIsSafe($plain, $matches[2])) {
                return $plain;
            }
        }

        if (preg_match('/^[a-f0-9]{16}_(.+)$/', $base, $matches) === 1) {
            return $matches[1];
        }

        return $base;
    }

    /** Nombre en disco: el original cifrado con AES-256-GCM y la extensión real al final. */
    public function protectedFileName(string $original, string $extension): string
    {
        $extension = strtolower($extension);
        $base = pathinfo($original, PATHINFO_FILENAME);
        $base = str_replace(["\0", "\r", "\n", '/', '\\', '"'], '', $base);
        $base = trim($base);
        if ($base === '') {
            $base = 'documento';
        }
        if (mb_strlen($base) > 180) {
            $base = mb_substr($base, 0, 180);
        }

        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($base . '.' . $extension, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if (!is_string($cipher) || !is_string($tag) || strlen($tag) !== 16) {
            throw new RuntimeException('No se pudo proteger el nombre del archivo.');
        }

        $token = rtrim(strtr(base64_encode($nonce . $tag . $cipher), '+/', '-_'), '=');

        return $token . '.' . $extension;
    }

    private function decryptName(string $token): ?string
    {
        $padded = strtr($token, '-_', '+/');
        $rest = strlen($padded) % 4;
        if ($rest > 0) {
            $padded .= str_repeat('=', 4 - $rest);
        }

        $raw = base64_decode($padded, true);
        if (!is_string($raw) || strlen($raw) < 29) {
            return null;
        }

        $plain = openssl_decrypt(
            substr($raw, 28),
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            substr($raw, 0, 12),
            substr($raw, 12, 16)
        );

        return is_string($plain) && $plain !== '' ? $plain : null;
    }

    private function nameIsSafe(string $name, string $extension): bool
    {
        if (strpbrk($name, "/\\\0\r\n\"") !== false) {
            return false;
        }

        return strtolower(pathinfo($name, PATHINFO_EXTENSION)) === $extension;
    }

    private function key(): string
    {
        $encoded = Env::get('DOCUMENT_KEY');
        $key = base64_decode($encoded, true);
        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('DOCUMENT_KEY debe ser una clave de 32 bytes en base64.');
        }

        return $key;
    }

    private function folder(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
    }
}
