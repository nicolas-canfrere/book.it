<?php

declare(strict_types=1);

namespace App\Room\Domain\Model;

use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;

final readonly class Room
{
    public function __construct(
        public string $id,
        public string $hotelId,
        public RoomNumber $number,
        public RoomFloor $floor,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
