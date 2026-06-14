<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomTypeId;

interface RoomTypeRepositoryInterface
{
    public function add(RoomType $roomType): void;

    public function get(RoomTypeId $id): ?RoomType;

    public function existsByHotelIdAndName(HotelId $hotelId, string $name): bool;

    public function update(RoomType $roomType): void;

    public function save(RoomType $roomType): void;

    public function delete(RoomTypeId $id): void;

    public function list(HotelId $hotelId, int $page, int $limit): RoomTypePage;
}
