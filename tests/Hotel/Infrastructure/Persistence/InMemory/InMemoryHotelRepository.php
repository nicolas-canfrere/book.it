<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Persistence\InMemory;

use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;

final class InMemoryHotelRepository implements HotelRepositoryInterface
{
    /** @var array<string, Hotel> */
    private array $hotels = [];

    public function add(Hotel $hotel): void
    {
        $this->hotels[$hotel->id] = $hotel;
    }

    public function get(string $id): ?Hotel
    {
        return $this->hotels[$id] ?? null;
    }
}
