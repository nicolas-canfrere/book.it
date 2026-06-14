<?php

declare(strict_types=1);

namespace App\Room\Application\Contract;

use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

interface RoomsByTypeFinderInterface
{
    /** @return RoomId[] */
    public function findByType(RoomTypeId $roomTypeId): array;
}
