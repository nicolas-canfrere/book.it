<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

interface RoomIdGeneratorInterface
{
    public function generate(): string;
}
