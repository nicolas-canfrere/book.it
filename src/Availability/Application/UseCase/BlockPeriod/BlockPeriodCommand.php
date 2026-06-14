<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\BlockPeriod;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\BlockedPeriodId;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class BlockPeriodCommand implements SyncCommandInterface
{
    public function __construct(
        public BlockedPeriodId $id,
        public RoomId $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
