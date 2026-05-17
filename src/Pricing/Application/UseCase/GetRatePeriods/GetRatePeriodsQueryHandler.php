<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetRatePeriods;

use App\Pricing\Domain\Model\RatePeriod;
use App\Pricing\Domain\Port\RatePeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetRatePeriodsQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private RatePeriodRepositoryInterface $repository,
    ) {
    }

    /** @return list<RatePeriod> */
    public function __invoke(GetRatePeriodsQuery $query): array
    {
        return $this->repository->findByRoomId($query->roomId);
    }
}
