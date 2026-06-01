<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\BlockPeriod;

use App\Availability\Domain\Exception\BlockedPeriodOverlapException;
use App\Availability\Domain\Exception\RoomNotFoundException;
use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Domain\Port\RoomExistsInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\BlockedPeriodCreated;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class BlockPeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BlockedPeriodRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(BlockPeriodCommand $command): void
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        if ($this->repository->hasOverlap($command->roomId, $command->checkIn, $command->checkOut)) {
            throw new BlockedPeriodOverlapException();
        }

        $this->repository->add(new BlockedPeriod(
            $command->id,
            $command->roomId,
            new DatePeriod($command->checkIn, $command->checkOut),
            $command->createdAt,
        ));

        $this->eventDispatcher->dispatch(new BlockedPeriodCreated(
            blockedPeriodId: $command->id,
            roomId: $command->roomId,
            checkIn: $command->checkIn,
            checkOut: $command->checkOut,
        ));
    }
}
