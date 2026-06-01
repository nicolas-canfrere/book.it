<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteAvailabilityHold;

use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\AvailabilityHoldDeleted;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DeleteAvailabilityHoldCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private AvailabilityHoldRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(DeleteAvailabilityHoldCommand $command): void
    {
        $this->repository->deleteByReservationId($command->reservationId);

        $this->eventDispatcher->dispatch(new AvailabilityHoldDeleted(
            reservationId: $command->reservationId,
        ));
    }
}
