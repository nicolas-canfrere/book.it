<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\SetCancellationPolicy;

final readonly class SetCancellationPolicyCommand
{
    public function __construct(
        public string $roomId,
        public int $daysThreshold,
    ) {
    }
}
