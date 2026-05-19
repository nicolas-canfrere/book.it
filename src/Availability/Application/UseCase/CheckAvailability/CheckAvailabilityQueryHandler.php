<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CheckAvailability;

use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class CheckAvailabilityQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private BlockedPeriodRepositoryInterface $blockedPeriodRepository,
        private AvailabilityHoldRepositoryInterface $holdRepository,
    ) {
    }

    public function __invoke(CheckAvailabilityQuery $query): bool
    {
        if ($this->blockedPeriodRepository->hasOverlap($query->roomId, $query->checkIn, $query->checkOut)) {
            return false;
        }

        return !$this->holdRepository->hasActiveOverlap($query->roomId, $query->checkIn, $query->checkOut);
    }
}
