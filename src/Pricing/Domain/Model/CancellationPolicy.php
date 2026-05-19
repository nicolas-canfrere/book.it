<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

final readonly class CancellationPolicy
{
    public function __construct(
        public string $roomId,
        public int $daysThreshold,
        public \DateTimeImmutable $updatedAt,
    ) {
        if ($daysThreshold < 1) {
            throw new \InvalidArgumentException('Days threshold must be at least 1.');
        }
    }
}
