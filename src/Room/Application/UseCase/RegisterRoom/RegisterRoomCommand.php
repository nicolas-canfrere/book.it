<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\RegisterRoom;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class RegisterRoomCommand implements SyncCommandInterface
{
    public function __construct(
        public RoomId $id,
        public string $hotelId,
        public string $number,
        public int $floor,
        public RoomTypeId $roomTypeId,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
