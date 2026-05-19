<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\SetCancellationPolicy;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class SetCancellationPolicyCommand implements SyncCommandInterface
{
    public function __construct(
        public string $roomId,
        public int $daysThreshold,
    ) {
    }
}
