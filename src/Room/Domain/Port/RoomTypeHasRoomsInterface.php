<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Shared\Domain\ValueObject\RoomTypeId;

interface RoomTypeHasRoomsInterface
{
    public function hasRooms(RoomTypeId $roomTypeId): bool;
}
