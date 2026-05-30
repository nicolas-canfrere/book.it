<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod;

use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final class DeleteBlockedPeriodByRoomAndPeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private readonly BlockedPeriodRepositoryInterface $blockedPeriods,
    ) {
    }

    public function __invoke(DeleteBlockedPeriodByRoomAndPeriodCommand $command): void
    {
        $this->blockedPeriods->removeByRoomAndPeriod($command->roomId, $command->checkIn, $command->checkOut);
    }
}
