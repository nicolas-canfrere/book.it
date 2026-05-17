<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetPromotions;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetPromotionsQuery implements SyncQueryInterface
{
    public function __construct(
        public string $roomId,
    ) {
    }
}
