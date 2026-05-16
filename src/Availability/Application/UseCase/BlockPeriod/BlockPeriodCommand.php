<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\BlockPeriod;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class BlockPeriodCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
