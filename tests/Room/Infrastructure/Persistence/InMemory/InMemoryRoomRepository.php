<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Persistence\InMemory;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\Port\RoomRepositoryInterface;

final class InMemoryRoomRepository implements RoomRepositoryInterface
{
    /** @var array<string, Room> */
    private array $rooms = [];

    public function add(Room $room): void
    {
        $this->rooms[$room->id] = $room;
    }

    public function get(string $id): ?Room
    {
        return $this->rooms[$id] ?? null;
    }

    public function existsByHotelIdAndNumber(string $hotelId, string $number): bool
    {
        foreach ($this->rooms as $room) {
            if ($room->hotelId === $hotelId && $room->number === $number) {
                return true;
            }
        }

        return false;
    }

    public function list(string $hotelId, int $page, int $limit): RoomPage
    {
        $filtered = array_values(array_filter(
            $this->rooms,
            static fn(Room $r) => $r->hotelId === $hotelId,
        ));

        usort($filtered, static fn(Room $a, Room $b) => strcmp($a->number, $b->number));

        $total = count($filtered);
        $rooms = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new RoomPage($rooms, $total);
    }
}
