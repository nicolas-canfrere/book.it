<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use App\Shared\Domain\ValueObject\RoomId;

final class FakeRoomCapacityFetcher implements RoomCapacityFetcherInterface
{
    private int $capacity = 10;

    public function setCapacity(int $capacity): void
    {
        $this->capacity = $capacity;
    }

    public function fetchCapacity(RoomId $roomId): int
    {
        return $this->capacity;
    }
}
