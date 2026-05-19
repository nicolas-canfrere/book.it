<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
