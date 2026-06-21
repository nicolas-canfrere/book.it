<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Shared\Domain\ValueObject\RoomId;

interface RoomBaseRateFinderInterface
{
    public function find(RoomId $roomId): ?int;
}
