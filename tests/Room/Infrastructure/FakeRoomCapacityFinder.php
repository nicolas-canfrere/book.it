<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure;

use App\Room\Domain\Port\RoomCapacityFinderInterface;

final class FakeRoomCapacityFinder implements RoomCapacityFinderInterface
{
    /** @var array<string, int> */
    private array $capacities = [];

    public function withCapacity(string $roomId, int $capacity): void
    {
        $this->capacities[$roomId] = $capacity;
    }

    public function findCapacity(string $roomId): int
    {
        return $this->capacities[$roomId] ?? 0;
    }
}
