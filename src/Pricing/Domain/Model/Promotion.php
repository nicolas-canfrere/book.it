<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use App\Shared\Domain\ValueObject\RoomId;

final class Promotion
{
    public function __construct(
        public readonly string $id,
        public readonly RoomId $roomId,
        private \DateTimeImmutable $checkIn,
        private \DateTimeImmutable $checkOut,
        private int $discountPercent,
        public readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
        if ($discountPercent < 1 || $discountPercent > 99) {
            throw new \InvalidArgumentException(
                sprintf('Discount percent must be between 1 and 99, %d given.', $discountPercent)
            );
        }
        if ($checkIn >= $checkOut) {
            throw new \InvalidArgumentException('Check-in date must be before check-out date.');
        }
    }

    public function getCheckIn(): \DateTimeImmutable
    {
        return $this->checkIn;
    }

    public function getCheckOut(): \DateTimeImmutable
    {
        return $this->checkOut;
    }

    public function getDiscountPercent(): int
    {
        return $this->discountPercent;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $discountPercent,
        \DateTimeImmutable $updatedAt,
    ): void {
        if ($discountPercent < 1 || $discountPercent > 99) {
            throw new \InvalidArgumentException(
                sprintf('Discount percent must be between 1 and 99, %d given.', $discountPercent)
            );
        }
        if ($checkIn >= $checkOut) {
            throw new \InvalidArgumentException('Check-in date must be before check-out date.');
        }
        $this->checkIn = $checkIn;
        $this->checkOut = $checkOut;
        $this->discountPercent = $discountPercent;
        $this->updatedAt = $updatedAt;
    }
}
