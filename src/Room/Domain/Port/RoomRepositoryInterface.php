<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;

interface RoomRepositoryInterface
{
    public function add(Room $room): void;

    public function get(string $id): ?Room;

    public function existsByHotelIdAndNumber(string $hotelId, string $number): bool;

    public function list(string $hotelId, int $page, int $limit): RoomPage;
}
