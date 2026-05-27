<?php

declare(strict_types=1);

namespace App\Notification\Domain\ReadModel;

final readonly class ReservationDetails
{
    public function __construct(
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPriceCents,
    ) {
    }
}
