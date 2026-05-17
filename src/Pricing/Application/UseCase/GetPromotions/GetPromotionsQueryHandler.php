<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetPromotions;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetPromotionsQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private PromotionRepositoryInterface $repository,
    ) {
    }

    /** @return list<Promotion> */
    public function __invoke(GetPromotionsQuery $query): array
    {
        return $this->repository->findByRoomId($query->roomId);
    }
}
