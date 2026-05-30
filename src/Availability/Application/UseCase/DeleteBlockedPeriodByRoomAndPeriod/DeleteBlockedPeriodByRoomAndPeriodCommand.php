<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeleteBlockedPeriodByRoomAndPeriodCommand implements SyncCommandInterface
{
    public function __construct(
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
