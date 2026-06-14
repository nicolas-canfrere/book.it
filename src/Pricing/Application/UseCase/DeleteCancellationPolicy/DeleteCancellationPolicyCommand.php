<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeleteCancellationPolicy;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class DeleteCancellationPolicyCommand implements SyncCommandInterface
{
    public function __construct(
        public RoomId $roomId,
    ) {
    }
}
