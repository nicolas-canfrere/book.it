<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Contract;

use App\Availability\Application\Contract\AvailabilityCheckerInterface;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;

final readonly class DoctrineAvailabilityChecker implements AvailabilityCheckerInterface
{
    public function __construct(
        private BlockedPeriodRepositoryInterface $blockedPeriods,
        private AvailabilityHoldRepositoryInterface $holds,
    ) {
    }

    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        if ($this->blockedPeriods->hasOverlap($roomId, $checkIn, $checkOut)) {
            return false;
        }

        return !$this->holds->hasActiveOverlap($roomId, $checkIn, $checkOut);
    }
}
