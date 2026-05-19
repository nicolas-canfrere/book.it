<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CreateAvailabilityHold;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class CreateAvailabilityHoldCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $roomId,
        public string $reservationId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
