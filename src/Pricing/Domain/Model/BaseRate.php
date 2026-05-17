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
    }
}
