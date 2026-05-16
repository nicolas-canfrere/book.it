<?php
declare(strict_types=1);

namespace App\Availability\Domain\Port;

interface RoomExistsInterface
{
    public function exists(string $roomId): bool;
}
