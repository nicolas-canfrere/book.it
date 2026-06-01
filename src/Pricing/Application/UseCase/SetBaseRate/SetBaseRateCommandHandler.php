<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\SetBaseRate;

use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Model\BaseRate;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\BaseRateSet;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class SetBaseRateCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BaseRateRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(SetBaseRateCommand $command): void
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        $this->repository->save(new BaseRate(
            roomId: $command->roomId,
            amountCents: $command->amountCents,
            updatedAt: $command->updatedAt,
        ));

        $this->eventDispatcher->dispatch(new BaseRateSet(
            roomId: $command->roomId,
            amountCents: $command->amountCents,
        ));
    }
}
