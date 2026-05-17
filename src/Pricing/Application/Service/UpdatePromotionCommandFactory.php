<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\UseCase\UpdatePromotion\UpdatePromotionCommand;
use Psr\Clock\ClockInterface;

final readonly class UpdatePromotionCommandFactory
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function create(string $promotionId, string $roomId, string $checkIn, string $checkOut, int $discountPercent): UpdatePromotionCommand
    {
        return new UpdatePromotionCommand(
            promotionId: $promotionId,
            roomId: $roomId,
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            discountPercent: $discountPercent,
            updatedAt: $this->clock->now(),
        );
    }
}
