<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\SetBaseRate;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class SetBaseRateCommand implements SyncCommandInterface
{
    public function __construct(
        public RoomId $roomId,
        public int $amountCents,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
