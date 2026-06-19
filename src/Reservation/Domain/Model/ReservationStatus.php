<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
