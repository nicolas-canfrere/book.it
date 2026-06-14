<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Shared\Domain\ValueObject\HotelId;

interface HotelIdGeneratorInterface
{
    public function generate(): HotelId;
}
