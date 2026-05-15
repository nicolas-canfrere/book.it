<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\Port\RoomRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomRepository implements RoomRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function add(Room $room): void
    {
        $this->bookit->insert('room', [
            'id' => $room->id,
            'hotel_id' => $room->hotelId,
            'number' => $room->number,
            'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Room
    {
        /** @var array{id: string, hotel_id: string, number: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, hotel_id, number, created_at FROM room WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Room($row['id'], $row['hotel_id'], $row['number'], new \DateTimeImmutable($row['created_at']));
    }

    public function existsByHotelIdAndNumber(string $hotelId, string $number): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM room WHERE hotel_id = :hotelId AND number = :number',
            ['hotelId' => $hotelId, 'number' => $number],
        );

        return $count > 0;
    }

    public function list(string $hotelId, int $page, int $limit): RoomPage
    {
        /** @var int|string $count */
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM room WHERE hotel_id = :hotelId',
            ['hotelId' => $hotelId],
        );
        $total = (int) $count;

        /** @var list<array{id: string, hotel_id: string, number: string, created_at: string}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            'SELECT id, hotel_id, number, created_at FROM room WHERE hotel_id = :hotelId ORDER BY number ASC LIMIT :limit OFFSET :offset',
            ['hotelId' => $hotelId, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
        );

        $rooms = array_map(
            fn(array $row) => new Room($row['id'], $row['hotel_id'], $row['number'], new \DateTimeImmutable($row['created_at'])),
            $rows,
        );

        return new RoomPage($rooms, $total);
    }
}
