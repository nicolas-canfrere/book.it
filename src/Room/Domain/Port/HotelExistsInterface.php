<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

interface HotelExistsInterface
{
    public function exists(string $hotelId): bool;
}
