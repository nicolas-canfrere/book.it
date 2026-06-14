<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller;

use App\Pricing\Domain\Model\Promotion;

final class PromotionSerializer
{
    /**
     * @return array{id: string, roomId: string, checkIn: string, checkOut: string, discountPercent: int, createdAt: string}
     */
    public function serialize(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'roomId' => $promotion->roomId->value,
            'checkIn' => $promotion->getCheckIn()->format('Y-m-d'),
            'checkOut' => $promotion->getCheckOut()->format('Y-m-d'),
            'discountPercent' => $promotion->getDiscountPercent(),
            'createdAt' => $promotion->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
