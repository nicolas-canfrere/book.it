<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Shared\Domain\ValueObject\RoomId;

interface RoomIdGeneratorInterface
{
    public function generate(): RoomId;
}
