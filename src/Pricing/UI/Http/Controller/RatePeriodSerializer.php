<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller;

use App\Pricing\Domain\Model\RatePeriod;

final class RatePeriodSerializer
{
    /**
     * @return array{id: string, roomId: string, checkIn: string, checkOut: string, amountCents: int, createdAt: string}
     */
    public function serialize(RatePeriod $ratePeriod): array
    {
        return [
            'id' => $ratePeriod->id,
            'roomId' => $ratePeriod->roomId->value,
            'checkIn' => $ratePeriod->checkIn->format('Y-m-d'),
            'checkOut' => $ratePeriod->checkOut->format('Y-m-d'),
            'amountCents' => $ratePeriod->amountCents,
            'createdAt' => $ratePeriod->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
