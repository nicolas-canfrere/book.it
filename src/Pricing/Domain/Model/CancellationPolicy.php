<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use App\Shared\Domain\ValueObject\RoomId;

final readonly class CancellationPolicy
{
    public function __construct(
        public RoomId $roomId,
        public int $daysThreshold,
        public \DateTimeImmutable $updatedAt,
    ) {
        if ($daysThreshold <= 0) {
            throw new \InvalidArgumentException('Days threshold must be greater than zero.');
        }
    }
}
