<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\CreatePromotion;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class CreatePromotionCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $roomId,
        public string $checkIn,
        public string $checkOut,
        public int $discountPercent,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
