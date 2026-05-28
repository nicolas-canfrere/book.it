<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;

final class FakeRoomCapacityFetcher implements RoomCapacityFetcherInterface
{
    private int $capacity = 10;

    public function setCapacity(int $capacity): void
    {
        $this->capacity = $capacity;
    }

    public function fetchCapacity(string $roomId): int
    {
        return $this->capacity;
    }
}
