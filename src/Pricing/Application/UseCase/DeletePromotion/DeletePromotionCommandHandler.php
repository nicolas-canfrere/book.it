<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeletePromotion;

use App\Pricing\Domain\Exception\PromotionNotFoundException;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeletePromotionCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
    ) {
    }

    public function __invoke(DeletePromotionCommand $command): void
    {
        $promotion = $this->promotionRepository->findById($command->promotionId);

        if (null === $promotion) {
            throw new PromotionNotFoundException($command->promotionId);
        }

        $this->promotionRepository->delete($promotion);
    }
}
