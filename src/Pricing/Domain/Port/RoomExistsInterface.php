<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

use App\Shared\Domain\ValueObject\RoomId;

interface RoomExistsInterface
{
    public function exists(RoomId $roomId): bool;
}
