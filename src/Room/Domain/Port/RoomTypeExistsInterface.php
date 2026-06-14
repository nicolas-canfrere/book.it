<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Shared\Domain\ValueObject\RoomTypeId;

interface RoomTypeExistsInterface
{
    public function exists(RoomTypeId $roomTypeId): bool;
}
