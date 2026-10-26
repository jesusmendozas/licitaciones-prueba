<?php

declare(strict_types=1);

namespace App\Core;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Arranca Eloquent en su versión independiente, sin el framework.
 * A partir de aquí los modelos llegan a MySQL solo por el ORM.
 * La zona de la conexión es Bogotá (UTC-5), la misma con la que se calcula el estado.
 */
class Database
{
    /** Deja la conexión lista para los modelos. */
    public static function boot(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => Env::get('DB_HOST'),
            'port' => Env::get('DB_PORT'),
            'database' => Env::get('DB_DATABASE'),
            'username' => Env::get('DB_USERNAME'),
            'password' => Env::get('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'timezone' => '-05:00',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
