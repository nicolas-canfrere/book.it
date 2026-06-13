<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Shared\Domain\ValueObject\BlockedPeriodId;

interface BlockedPeriodRepositoryInterface
{
    public function add(BlockedPeriod $period): void;

    public function get(BlockedPeriodId $id): ?BlockedPeriod;

    public function remove(BlockedPeriodId $id): void;

    public function hasOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;

    /** @return list<BlockedPeriod> */
    public function listByRoomId(string $roomId): array;

    public function removeByRoomAndPeriod(
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void;
}
