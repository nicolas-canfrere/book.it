<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod;

use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\BlockedPeriodDeleted;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DeleteBlockedPeriodByRoomAndPeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private readonly BlockedPeriodRepositoryInterface $blockedPeriods,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(DeleteBlockedPeriodByRoomAndPeriodCommand $command): void
    {
        $this->blockedPeriods->removeByRoomAndPeriod($command->roomId, $command->checkIn, $command->checkOut);

        $this->eventDispatcher->dispatch(new BlockedPeriodDeleted(
            roomId: $command->roomId,
            checkIn: $command->checkIn,
            checkOut: $command->checkOut,
        ));
    }
}
