<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CreateAvailabilityHold;

use App\Availability\Domain\Exception\AvailabilityHoldOverlapException;
use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class CreateAvailabilityHoldCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private AvailabilityHoldRepositoryInterface $repository)
    {
    }

    public function __invoke(CreateAvailabilityHoldCommand $command): void
    {
        if ($this->repository->hasActiveOverlap($command->roomId, $command->checkIn, $command->checkOut)) {
            throw new AvailabilityHoldOverlapException();
        }

        $this->repository->add(new AvailabilityHold(
            id: $command->id,
            roomId: $command->roomId,
            reservationId: $command->reservationId,
            period: new DatePeriod($command->checkIn, $command->checkOut),
            expiresAt: $command->expiresAt,
            createdAt: $command->createdAt,
        ));
    }
}
