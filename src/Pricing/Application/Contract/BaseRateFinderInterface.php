<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

use App\Shared\Domain\ValueObject\RoomId;

interface BaseRateFinderInterface
{
    /**
     * @param list<RoomId> $roomIds
     *
     * @return array<string, BaseRateView>
     */
    public function findByRoomIds(array $roomIds): array;
}
