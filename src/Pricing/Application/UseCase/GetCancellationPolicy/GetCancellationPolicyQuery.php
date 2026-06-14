<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetCancellationPolicy;

use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\RoomId;

/** @implements SyncQueryInterface<\App\Pricing\Domain\Model\CancellationPolicy> */
final readonly class GetCancellationPolicyQuery implements SyncQueryInterface
{
    public function __construct(
        public RoomId $roomId,
    ) {
    }
}
