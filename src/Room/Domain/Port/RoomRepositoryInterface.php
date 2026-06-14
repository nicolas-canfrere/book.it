<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;

interface RoomRepositoryInterface
{
    public function add(Room $room): void;

    /** @param list<Room> $rooms */
    public function addAll(array $rooms): void;

    public function get(RoomId $id): ?Room;

    public function existsByHotelIdAndNumber(HotelId $hotelId, string $number): bool;

    public function list(HotelId $hotelId, int $page, int $limit): RoomPage;
}
