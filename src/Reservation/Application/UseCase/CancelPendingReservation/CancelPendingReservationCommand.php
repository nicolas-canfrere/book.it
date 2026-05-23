<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CancelPendingReservation;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class CancelPendingReservationCommand implements SyncCommandInterface
{
    public function __construct(public string $reservationId)
    {
    }
}
