<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

interface AvailableRoomPickerInterface
{
    public function pick(RoomTypeId $roomTypeId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): ?RoomId;
}
