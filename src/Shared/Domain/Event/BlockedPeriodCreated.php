<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class BlockedPeriodCreated
{
    public function __construct(
        public string $blockedPeriodId,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
