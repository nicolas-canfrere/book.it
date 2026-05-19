<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeleteCancellationPolicy;

final readonly class DeleteCancellationPolicyCommand
{
    public function __construct(
        public string $roomId,
    ) {
    }
}
