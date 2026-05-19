<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteAvailabilityHold;

use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeleteAvailabilityHoldCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private AvailabilityHoldRepositoryInterface $repository)
    {
    }

    public function __invoke(DeleteAvailabilityHoldCommand $command): void
    {
        $this->repository->deleteByReservationId($command->reservationId);
    }
}
