<?php

declare(strict_types=1);

namespace App\Hotel\Application\Contract;

final readonly class HotelView
{
    public function __construct(public string $id)
    {
    }
}
