<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeleteRatePeriod;

use App\Pricing\Domain\Exception\RatePeriodNotFoundException;
use App\Pricing\Domain\Port\RatePeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeleteRatePeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RatePeriodRepositoryInterface $repository,
    ) {
    }

    public function __invoke(DeleteRatePeriodCommand $command): void
    {
        $ratePeriod = $this->repository->findById($command->ratePeriodId);

        if (null === $ratePeriod) {
            throw new RatePeriodNotFoundException($command->ratePeriodId);
        }

        $this->repository->delete($ratePeriod);
    }
}
