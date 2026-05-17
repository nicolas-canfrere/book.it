<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

final readonly class BaseRate
{
    public function __construct(
        public string $roomId,
        public int $amountCents,
        public \DateTimeImmutable $updatedAt,
    ) {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Amount in cents must be greater than zero.');
        }
    }
}
