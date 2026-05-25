<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomTypeHasRoomsInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomTypeHasRoomsChecker implements RoomTypeHasRoomsInterface
{
    public function __construct(private Connection $bookit) {}

    public function hasRooms(string $roomTypeId): bool
    {
        // Stub — returns false until room.room_type_id column is added in Plan B
        return false;
    }
}
