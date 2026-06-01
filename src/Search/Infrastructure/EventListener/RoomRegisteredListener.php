<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\RoomRegistered;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomRegistered::class)]
final readonly class RoomRegisteredListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(RoomRegistered $event): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO room_index (room_id, room_type_id, hotel_id)
            VALUES (:roomId, :roomTypeId, :hotelId)
            ON CONFLICT (room_id) DO UPDATE SET
                room_type_id = EXCLUDED.room_type_id,
                hotel_id     = EXCLUDED.hotel_id
            SQL,
            [
                'roomId' => $event->roomId,
                'roomTypeId' => $event->roomTypeId,
                'hotelId' => $event->hotelId,
            ],
        );
    }
}
