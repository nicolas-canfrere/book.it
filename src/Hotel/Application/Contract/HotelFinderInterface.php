<?php

declare(strict_types=1);

namespace App\Hotel\Application\Contract;

use App\Shared\Domain\ValueObject\HotelId;

interface HotelFinderInterface
{
    public function find(HotelId $hotelId): ?HotelView;
}
