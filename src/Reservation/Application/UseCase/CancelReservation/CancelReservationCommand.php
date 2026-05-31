<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CancelReservation;

final readonly class CancelReservationCommand
{
    public function __construct(
        public string $reservationId,
        public \DateTimeImmutable $today,
    ) {
    }
}
