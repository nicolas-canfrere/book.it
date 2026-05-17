<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\CreateRatePeriod;

use App\Pricing\Domain\Exception\RatePeriodOverlapException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Model\RatePeriod;
use App\Pricing\Domain\Port\RatePeriodRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class CreateRatePeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RatePeriodRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
    ) {
    }

    public function __invoke(CreateRatePeriodCommand $command): void
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        $period = new DatePeriod($command->checkIn, $command->checkOut);

        if ($this->repository->hasOverlap($command->roomId, $period)) {
            throw new RatePeriodOverlapException();
        }

        $this->repository->save(new RatePeriod(
            $command->id,
            $command->roomId,
            $command->checkIn,
            $command->checkOut,
            $command->amountCents,
            $command->createdAt,
            $command->updatedAt,
        ));
    }
}
