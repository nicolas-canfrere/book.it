<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Shared\Domain\ValueObject\RoomId;

interface RoomBaseRateFinderInterface
{
    /**
     * @param list<RoomId> $roomIds
     *
     * @return array<string, int>
     */
    public function findByRoomIds(array $roomIds): array;
}
