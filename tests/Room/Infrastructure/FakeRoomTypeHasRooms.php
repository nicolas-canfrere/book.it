<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure;

use App\Room\Domain\Port\RoomTypeHasRoomsInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;

final class FakeRoomTypeHasRooms implements RoomTypeHasRoomsInterface
{
    private bool $hasRooms = false;

    public function setHasRooms(bool $hasRooms): void
    {
        $this->hasRooms = $hasRooms;
    }

    public function hasRooms(RoomTypeId $roomTypeId): bool
    {
        return $this->hasRooms;
    }
}
