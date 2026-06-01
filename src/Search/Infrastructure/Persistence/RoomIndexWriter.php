<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Persistence;

use App\Search\Domain\Port\RoomIndexWriterInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomIndexWriter implements RoomIndexWriterInterface
{
    public function __construct(private Connection $searchConnection)
    {
    }

    public function upsert(string $roomId, string $roomTypeId, string $hotelId): void
    {
        $this->searchConnection->executeStatement(
            <<<'SQL'
            INSERT INTO room_index (room_id, room_type_id, hotel_id)
            VALUES (:roomId, :roomTypeId, :hotelId)
            ON CONFLICT (room_id) DO UPDATE SET
                room_type_id = EXCLUDED.room_type_id,
                hotel_id     = EXCLUDED.hotel_id
            SQL,
            ['roomId' => $roomId, 'roomTypeId' => $roomTypeId, 'hotelId' => $hotelId],
        );
    }
}
