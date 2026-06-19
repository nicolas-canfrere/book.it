<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

enum ReservationPeriodFilter: string
{
    case Past = 'past';
    case Current = 'current';
    case Upcoming = 'upcoming';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
