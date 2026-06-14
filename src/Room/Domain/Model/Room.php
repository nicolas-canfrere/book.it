<?php

declare(strict_types=1);

namespace App\Room\Domain\Model;

use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class Room
{
    public function __construct(
        public RoomId $id,
        public HotelId $hotelId,
        public RoomNumber $number,
        public RoomFloor $floor,
        public RoomTypeId $roomTypeId,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
