<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CheckAvailability;

use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class CheckAvailabilityQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private BlockedPeriodRepositoryInterface $repository)
    {
    }

    public function __invoke(CheckAvailabilityQuery $query): bool
    {
        return !$this->repository->hasOverlap($query->roomId, $query->checkIn, $query->checkOut);
    }
}
