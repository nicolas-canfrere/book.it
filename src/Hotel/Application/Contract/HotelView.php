<?php

declare(strict_types=1);

namespace App\Hotel\Application\Contract;

// Intentionally minimal: current consumers only need existence (id). Extend when a consumer requires more fields.
final readonly class HotelView
{
    public function __construct(public string $id)
    {
    }
}
