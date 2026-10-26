<?php

declare(strict_types=1);

/**
 * Crea la base y las tablas con PHP, y después carga las actividades.
 * Sirve cuando MySQL está encendido pero el comando mysql no está en la terminal.
 */

use App\Core\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$host = Env::get('DB_HOST', '127.0.0.1');
$port = Env::get('DB_PORT', '3306');
$database = Env::get('DB_DATABASE', 'licitaciones');
$user = Env::get('DB_USERNAME', 'root');
$password = Env::get('DB_PASSWORD', '');

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "PHP no tiene la extensión pdo_mysql. Actívala en php.ini y vuelve a correr este archivo.\n");
    exit(1);
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "DB_DATABASE solo puede tener letras, números y guion bajo.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        $database
    ));
    $pdo->exec(sprintf('USE `%s`', $database));

    $sql = file_get_contents(__DIR__ . '/01_estructura.sql');
    if ($sql === false) {
        throw new RuntimeException('No se pudo leer bd/01_estructura.sql.');
    }

    foreach (schemaStatements($sql) as $statement) {
        $pdo->exec($statement);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo preparar la base. ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Base {$database} lista." . PHP_EOL;

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/load_activities.php');
passthru($command, $exitCode);
exit($exitCode);

/**
 * Sentencias de las tablas. La base ya se creó con el nombre del .env.
 *
 * @return array<int, string>
 */
function schemaStatements(string $sql): array
{
    $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql);
    $parts = explode(';', $withoutComments ?? $sql);
    $statements = [];

    foreach ($parts as $part) {
        $statement = trim($part);
        if ($statement === '') {
            continue;
        }
        if (preg_match('/^(CREATE\s+DATABASE|USE\s+|SET\s+NAMES\s+)/i', $statement)) {
            continue;
        }
        $statements[] = $statement;
    }

    return $statements;
}
