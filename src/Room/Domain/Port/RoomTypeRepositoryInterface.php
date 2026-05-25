<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;

interface RoomTypeRepositoryInterface
{
    public function add(RoomType $roomType): void;

    public function get(string $id): ?RoomType;

    public function existsByHotelIdAndName(string $hotelId, string $name): bool;

    public function update(RoomType $roomType): void;

    public function delete(string $id): void;

    public function list(string $hotelId, int $page, int $limit): RoomTypePage;
}
