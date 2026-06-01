<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Availability\Domain\Exception\BlockedPeriodNotFoundException;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\BlockedPeriodDeleted;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DeleteBlockedPeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BlockedPeriodRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(DeleteBlockedPeriodCommand $command): void
    {
        $blockedPeriod = $this->repository->get($command->id);

        if (null === $blockedPeriod) {
            throw new BlockedPeriodNotFoundException($command->id);
        }

        $this->repository->remove($command->id);

        $this->eventDispatcher->dispatch(new BlockedPeriodDeleted(
            roomId: $blockedPeriod->roomId,
            checkIn: $blockedPeriod->period->checkIn,
            checkOut: $blockedPeriod->period->checkOut,
        ));
    }
}
