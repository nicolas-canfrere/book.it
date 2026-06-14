<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Persistence;

use App\Search\Domain\Port\RoomIndexWriterInterface;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;
use Doctrine\DBAL\Connection;

final readonly class RoomIndexWriter implements RoomIndexWriterInterface
{
    public function __construct(private Connection $searchConnection)
    {
    }

    public function upsert(RoomId $roomId, string $roomTypeId, HotelId $hotelId): void
    {
        $this->searchConnection->executeStatement(
            <<<'SQL'
            INSERT INTO room_index (room_id, room_type_id, hotel_id)
            VALUES (:roomId, :roomTypeId, :hotelId)
            ON CONFLICT (room_id) DO UPDATE SET
                room_type_id = EXCLUDED.room_type_id,
                hotel_id     = EXCLUDED.hotel_id
            SQL,
            ['roomId' => $roomId->value, 'roomTypeId' => $roomTypeId, 'hotelId' => $hotelId->value],
        );
    }
}
