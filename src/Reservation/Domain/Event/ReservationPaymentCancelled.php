<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Event;

final readonly class ReservationPaymentCancelled
{
    public function __construct(
        public string $reservationId,
    ) {
    }
}
