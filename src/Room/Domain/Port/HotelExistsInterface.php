<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Shared\Domain\ValueObject\HotelId;

interface HotelExistsInterface
{
    public function exists(HotelId $hotelId): bool;
}
