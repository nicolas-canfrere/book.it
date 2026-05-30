<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use Doctrine\DBAL\Connection;

final readonly class RoomRepository implements RoomRepositoryInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function add(Room $room): void
    {
        $this->roomConnection->insert('room', [
            'id' => $room->id,
            'hotel_id' => $room->hotelId,
            'room_number' => $room->number->value,
            'room_floor' => $room->floor->value,
            'room_type_id' => $room->roomTypeId,
            'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function addAll(array $rooms): void
    {
        $connection = $this->roomConnection;
        $this->roomConnection->transactional(static function () use ($rooms, $connection): void {
            foreach ($rooms as $room) {
                $connection->insert('room', [
                    'id' => $room->id,
                    'hotel_id' => $room->hotelId,
                    'room_number' => $room->number->value,
                    'room_floor' => $room->floor->value,
                    'room_type_id' => $room->roomTypeId,
                    'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    public function get(string $id): ?Room
    {
        /** @var array{id: string, hotel_id: string, room_number: string, room_floor: int|string, room_type_id: string, created_at: string}|false $row */
        $row = $this->roomConnection->fetchAssociative(
            'SELECT id, hotel_id, room_number, room_floor, room_type_id, created_at FROM room WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Room(
            $row['id'],
            $row['hotel_id'],
            new RoomNumber($row['room_number']),
            new RoomFloor((int) $row['room_floor']),
            $row['room_type_id'],
            new \DateTimeImmutable($row['created_at']),
        );
    }

    public function existsByHotelIdAndNumber(string $hotelId, string $number): bool
    {
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room WHERE hotel_id = :hotelId AND room_number = :number',
            ['hotelId' => $hotelId, 'number' => $number],
        );

        return $count > 0;
    }

    public function list(string $hotelId, int $page, int $limit): RoomPage
    {
        /** @var int|string $count */
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room WHERE hotel_id = :hotelId',
            ['hotelId' => $hotelId],
        );
        $total = (int) $count;

        /** @var list<array{id: string, hotel_id: string, room_number: string, room_floor: int|string, room_type_id: string, created_at: string}> $rows */
        $rows = $this->roomConnection->fetchAllAssociative(
            'SELECT id, hotel_id, room_number, room_floor, room_type_id, created_at FROM room WHERE hotel_id = :hotelId ORDER BY room_number ASC LIMIT :limit OFFSET :offset',
            ['hotelId' => $hotelId, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
        );

        $rooms = array_map(
            fn(array $row) => new Room(
                $row['id'],
                $row['hotel_id'],
                new RoomNumber($row['room_number']),
                new RoomFloor((int) $row['room_floor']),
                $row['room_type_id'],
                new \DateTimeImmutable($row['created_at']),
            ),
            $rows,
        );

        return new RoomPage($rooms, $total);
    }
}
