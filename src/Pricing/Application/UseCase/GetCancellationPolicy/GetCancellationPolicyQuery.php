<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetCancellationPolicy;

final readonly class GetCancellationPolicyQuery
{
    public function __construct(
        public string $roomId,
    ) {
    }
}
