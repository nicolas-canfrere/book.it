<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\UpdatePromotion;

use App\Pricing\Domain\Exception\PromotionNotFoundException;
use App\Pricing\Domain\Exception\PromotionOverlapException;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class UpdatePromotionCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
    ) {
    }

    public function __invoke(UpdatePromotionCommand $command): void
    {
        $promotion = $this->promotionRepository->findById($command->promotionId);

        if (null === $promotion) {
            throw new PromotionNotFoundException($command->promotionId);
        }

        $period = new DatePeriod($command->checkIn, $command->checkOut);

        if ($this->promotionRepository->hasOverlap($command->roomId, $period, $command->promotionId)) {
            throw new PromotionOverlapException();
        }

        $promotion->update($period->checkIn, $period->checkOut, $command->discountPercent, $command->updatedAt);

        $this->promotionRepository->save($promotion);
    }
}
