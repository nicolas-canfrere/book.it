<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Availability\Domain\Model\BlockedPeriod;

interface BlockedPeriodRepositoryInterface
{
    public function add(BlockedPeriod $period): void;

    public function get(string $id): ?BlockedPeriod;

    public function remove(string $id): void;

    public function hasOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;

    /** @return list<BlockedPeriod> */
    public function listByRoomId(string $roomId): array;
}
