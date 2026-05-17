<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\UpdateRatePeriod;

use App\Pricing\Domain\Exception\RatePeriodNotFoundException;
use App\Pricing\Domain\Exception\RatePeriodOverlapException;
use App\Pricing\Domain\Model\RatePeriod;
use App\Pricing\Domain\Port\RatePeriodRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class UpdateRatePeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RatePeriodRepositoryInterface $repository,
    ) {
    }

    public function __invoke(UpdateRatePeriodCommand $command): void
    {
        $ratePeriod = $this->repository->findById($command->ratePeriodId);

        if (null === $ratePeriod) {
            throw new RatePeriodNotFoundException($command->ratePeriodId);
        }

        $period = new DatePeriod($command->checkIn, $command->checkOut);

        if ($this->repository->hasOverlap($command->roomId, $period, $command->ratePeriodId)) {
            throw new RatePeriodOverlapException();
        }

        $this->repository->save(new RatePeriod(
            id: $command->ratePeriodId,
            roomId: $command->roomId,
            checkIn: $command->checkIn,
            checkOut: $command->checkOut,
            amountCents: $command->amountCents,
            createdAt: $ratePeriod->createdAt,
            updatedAt: $command->updatedAt,
        ));
    }
}
