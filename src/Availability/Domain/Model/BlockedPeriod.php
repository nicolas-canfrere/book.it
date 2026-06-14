<?php

declare(strict_types=1);

namespace App\Availability\Domain\Model;

use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Domain\ValueObject\BlockedPeriodId;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class BlockedPeriod
{
    public function __construct(
        public BlockedPeriodId $id,
        public RoomId $roomId,
        public DatePeriod $period,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
