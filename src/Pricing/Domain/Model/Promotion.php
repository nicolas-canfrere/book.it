<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

final class Promotion
{
    public function __construct(
        public readonly string $id,
        public readonly string $roomId,
        private \DateTimeImmutable $checkIn,
        private \DateTimeImmutable $checkOut,
        private int $discountPercent,
        public readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

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
        $this->checkIn = $checkIn;
        $this->checkOut = $checkOut;
        $this->discountPercent = $discountPercent;
        $this->updatedAt = $updatedAt;
    }
}
