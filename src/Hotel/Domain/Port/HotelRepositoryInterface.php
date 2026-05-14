<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Hotel\Domain\Model\Hotel;

interface HotelRepositoryInterface
{
    public function add(Hotel $hotel): void;

    public function get(string $id): ?Hotel;
}
