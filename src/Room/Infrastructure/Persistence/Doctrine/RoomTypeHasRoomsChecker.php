<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomTypeHasRoomsInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Doctrine\DBAL\Connection;

final readonly class RoomTypeHasRoomsChecker implements RoomTypeHasRoomsInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function hasRooms(RoomTypeId $roomTypeId): bool
    {
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room WHERE room_type_id = :id',
            ['id' => $roomTypeId->value],
        );

        return $count > 0;
    }
}
