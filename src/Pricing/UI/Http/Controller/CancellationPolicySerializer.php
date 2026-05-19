<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller;

use App\Pricing\Domain\Model\CancellationPolicy;

final readonly class CancellationPolicySerializer
{
    /**
     * @return array{room_id: string, days_threshold: int, updated_at: string}
     */
    public function serialize(CancellationPolicy $policy): array
    {
        return [
            'room_id' => $policy->roomId,
            'days_threshold' => $policy->daysThreshold,
            'updated_at' => $policy->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
