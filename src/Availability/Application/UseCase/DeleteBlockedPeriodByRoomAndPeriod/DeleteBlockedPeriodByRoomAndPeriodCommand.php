<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class DeleteBlockedPeriodByRoomAndPeriodCommand implements SyncCommandInterface
{
    public function __construct(
        public RoomId $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
