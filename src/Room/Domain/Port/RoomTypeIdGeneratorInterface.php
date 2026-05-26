<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

interface RoomTypeIdGeneratorInterface
{
    public function generate(): string;
}
