<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetBlockedPeriod;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetBlockedPeriodQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private BlockedPeriodRepositoryInterface $repository)
    {
    }

    public function __invoke(GetBlockedPeriodQuery $query): ?BlockedPeriod
    {
        return $this->repository->get($query->id);
    }
}
