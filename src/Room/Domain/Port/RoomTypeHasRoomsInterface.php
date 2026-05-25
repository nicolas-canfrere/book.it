<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

interface RoomTypeHasRoomsInterface
{
    public function hasRooms(string $roomTypeId): bool;
}
