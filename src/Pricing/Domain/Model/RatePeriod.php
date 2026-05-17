<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

final readonly class RatePeriod
{
    public function __construct(
        public string $id,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $amountCents,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
