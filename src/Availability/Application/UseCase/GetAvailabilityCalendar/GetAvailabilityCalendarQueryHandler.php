<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetAvailabilityCalendar;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetAvailabilityCalendarQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private BlockedPeriodRepositoryInterface $repository)
    {
    }

    /** @return list<BlockedPeriod> */
    public function __invoke(GetAvailabilityCalendarQuery $query): array
    {
        return $this->repository->listByRoomId($query->roomId);
    }
}
