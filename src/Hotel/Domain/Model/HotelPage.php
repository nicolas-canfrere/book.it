<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Model;

final readonly class HotelPage
{
    /** @param list<Hotel> $hotels */
    public function __construct(
        public array $hotels,
        public int $total,
    ) {
    }
}
