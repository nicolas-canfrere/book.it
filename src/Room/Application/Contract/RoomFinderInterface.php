<?php

declare(strict_types=1);

namespace App\Room\Application\Contract;

interface RoomFinderInterface
{
    public function find(string $roomId): ?RoomView;
}
