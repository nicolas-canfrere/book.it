<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetBaseRate;

use App\Pricing\Domain\Exception\BaseRateNotFoundException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Model\BaseRate;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetBaseRateQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private BaseRateRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
    ) {
    }

    public function __invoke(GetBaseRateQuery $query): BaseRate
    {
        if (!$this->roomExists->exists($query->roomId)) {
            throw new RoomNotFoundException($query->roomId);
        }

        $baseRate = $this->repository->findByRoomId($query->roomId);

        if (null === $baseRate) {
            throw new BaseRateNotFoundException($query->roomId);
        }

        return $baseRate;
    }
}
