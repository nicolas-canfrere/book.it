<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RegisterSearchRoom;

use App\Shared\Application\Bus\AsyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class RegisterSearchRoomCommand implements AsyncCommandInterface
{
    public function __construct(
        public RoomId $roomId,
        public HotelId $hotelId,
        public RoomTypeId $roomTypeId,
    ) {
    }
}
