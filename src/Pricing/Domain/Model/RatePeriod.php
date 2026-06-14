<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use App\Shared\Domain\ValueObject\RoomId;

final readonly class RatePeriod
{
    public function __construct(
        public string $id,
        public RoomId $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $amountCents,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Amount in cents must be greater than zero.');
        }
        if ($checkIn >= $checkOut) {
            throw new \InvalidArgumentException('Check-in date must be before check-out date.');
        }
    }
}
