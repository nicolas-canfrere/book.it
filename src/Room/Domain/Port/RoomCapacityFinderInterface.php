<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

interface RoomCapacityFinderInterface
{
    public function findCapacity(string $roomId): int;
}
