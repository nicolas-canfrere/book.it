<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller;

use App\Pricing\Domain\Model\BaseRate;

final class BaseRateSerializer
{
    /**
     * @return array{roomId: string, amountCents: int, updatedAt: string}
     */
    public function serialize(BaseRate $baseRate): array
    {
        return [
            'roomId' => $baseRate->roomId->value,
            'amountCents' => $baseRate->amountCents,
            'updatedAt' => $baseRate->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
