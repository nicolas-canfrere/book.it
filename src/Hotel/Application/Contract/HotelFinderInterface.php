<?php

declare(strict_types=1);

namespace App\Hotel\Application\Contract;

interface HotelFinderInterface
{
    public function find(string $hotelId): ?HotelView;
}
