<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

interface RoomExistsInterface
{
    public function exists(string $roomId): bool;
}
