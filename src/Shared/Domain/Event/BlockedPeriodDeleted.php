<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class BlockedPeriodDeleted
{
    public function __construct(
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
