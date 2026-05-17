<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeletePromotion;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeletePromotionCommand implements SyncCommandInterface
{
    public function __construct(
        public string $promotionId,
    ) {
    }
}
