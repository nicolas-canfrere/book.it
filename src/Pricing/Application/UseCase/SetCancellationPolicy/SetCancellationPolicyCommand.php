<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\SetCancellationPolicy;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class SetCancellationPolicyCommand implements SyncCommandInterface
{
    public function __construct(
        public RoomId $roomId,
        public int $daysThreshold,
    ) {
    }
}
