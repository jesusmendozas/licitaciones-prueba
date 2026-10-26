<?php

declare(strict_types=1);

namespace App\Support;

use DateTime;
use DateTimeZone;

/**
 * Reloj de la aplicación. El estado se calcula en America/Bogota aunque
 * el servidor esté en otra zona: la vigencia de la oferta es la hora de Colombia.
 */
class Clock
{
    public const ZONA = 'America/Bogota';

    /** Instante actual en America/Bogota. */
    public function now(): DateTime
    {
        return new DateTime('now', new DateTimeZone(self::ZONA));
    }

    /** El mismo instante, como texto Y-m-d H:i:s para guardarlo en MySQL. */
    public function timestamp(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }
}
