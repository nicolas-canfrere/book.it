<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetCancellationPolicy;

use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<\App\Pricing\Domain\Model\CancellationPolicy> */
final readonly class GetCancellationPolicyQuery implements SyncQueryInterface
{
    public function __construct(
        public string $roomId,
    ) {
    }
}
