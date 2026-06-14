<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Persistence\InMemory;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomTypeId;

final class InMemoryRoomTypeRepository implements RoomTypeRepositoryInterface
{
    /** @var array<string, RoomType> */
    private array $roomTypes = [];

    public function add(RoomType $roomType): void
    {
        $this->roomTypes[$roomType->id->value] = $roomType;
    }

    public function get(RoomTypeId $id): ?RoomType
    {
        return $this->roomTypes[$id->value] ?? null;
    }

    public function existsByHotelIdAndName(HotelId $hotelId, string $name): bool
    {
        foreach ($this->roomTypes as $roomType) {
            if ($roomType->hotelId->equals($hotelId) && $roomType->name === $name) {
                return true;
            }
        }

        return false;
    }

    public function update(RoomType $roomType): void
    {
        $this->roomTypes[$roomType->id->value] = $roomType;
    }

    public function save(RoomType $roomType): void
    {
        $this->roomTypes[$roomType->id->value] = $roomType;
    }

    public function delete(RoomTypeId $id): void
    {
        unset($this->roomTypes[$id->value]);
    }

    public function list(HotelId $hotelId, int $page, int $limit): RoomTypePage
    {
        $filtered = array_values(array_filter(
            $this->roomTypes,
            static fn(RoomType $rt) => $rt->hotelId->equals($hotelId),
        ));

        usort($filtered, static fn(RoomType $a, RoomType $b) => strcmp($a->name, $b->name));

        $total = count($filtered);
        $roomTypes = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new RoomTypePage($roomTypes, $total);
    }
}
