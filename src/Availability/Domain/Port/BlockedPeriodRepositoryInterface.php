<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Shared\Domain\ValueObject\BlockedPeriodId;
use App\Shared\Domain\ValueObject\RoomId;

interface BlockedPeriodRepositoryInterface
{
    public function add(BlockedPeriod $period): void;

    public function get(BlockedPeriodId $id): ?BlockedPeriod;

    public function remove(BlockedPeriodId $id): void;

    public function hasOverlap(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;

    /** @return list<BlockedPeriod> */
    public function listByRoomId(RoomId $roomId): array;

    public function removeByRoomAndPeriod(
        RoomId $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void;
}
