<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Persistence\InMemory;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;

final class InMemoryRoomRepository implements RoomRepositoryInterface
{
    /** @var array<string, Room> */
    private array $rooms = [];

    public function add(Room $room): void
    {
        $this->rooms[$room->id->value] = $room;
    }

    public function addAll(array $rooms): void
    {
        foreach ($rooms as $room) {
            $this->add($room);
        }
    }

    public function get(RoomId $id): ?Room
    {
        return $this->rooms[$id->value] ?? null;
    }

    public function existsByHotelIdAndNumber(HotelId $hotelId, string $number): bool
    {
        foreach ($this->rooms as $room) {
            if ($room->hotelId->equals($hotelId) && $room->number->value === $number) {
                return true;
            }
        }

        return false;
    }

    public function list(HotelId $hotelId, int $page, int $limit): RoomPage
    {
        $filtered = array_values(array_filter(
            $this->rooms,
            static fn(Room $r) => $r->hotelId->equals($hotelId),
        ));

        usort($filtered, static fn(Room $a, Room $b) => strcmp($a->number->value, $b->number->value));

        $total = count($filtered);
        $rooms = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new RoomPage($rooms, $total);
    }
}
