<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface RoomCapacityFetcherInterface
{
    public function fetchCapacity(string $roomId): int;
}
