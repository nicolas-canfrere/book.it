<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class AvailabilityHoldCreated
{
    public function __construct(
        public string $holdId,
        public string $roomId,
        public string $reservationId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
