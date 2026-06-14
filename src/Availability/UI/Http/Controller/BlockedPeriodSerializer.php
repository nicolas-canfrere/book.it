<?php

declare(strict_types=1);

namespace App\Availability\UI\Http\Controller;

use App\Availability\Domain\Model\BlockedPeriod;

final class BlockedPeriodSerializer
{
    /**
     * @return array{id: string, roomId: string, checkIn: string, checkOut: string, createdAt: string}
     */
    public function serialize(BlockedPeriod $period): array
    {
        return [
            'id' => $period->id->value,
            'roomId' => $period->roomId->value,
            'checkIn' => $period->period->checkIn->format('Y-m-d'),
            'checkOut' => $period->period->checkOut->format('Y-m-d'),
            'createdAt' => $period->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
