<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CancelReservation;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class CancelReservationCommand implements SyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public \DateTimeImmutable $today,
    ) {
    }
}
