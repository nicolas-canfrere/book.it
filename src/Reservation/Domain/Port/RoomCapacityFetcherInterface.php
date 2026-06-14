<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Shared\Domain\ValueObject\RoomId;

interface RoomCapacityFetcherInterface
{
    public function fetchCapacity(RoomId $roomId): int;
}
