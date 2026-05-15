<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Persistence\InMemory;

use App\Hotel\Domain\Model\Address;
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

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $key = $this->normalize($name, $address);

        foreach ($this->hotels as $hotel) {
            if ($this->normalize($hotel->name, $hotel->address) === $key) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $name, Address $address): string
    {
        return implode('|', array_map(
            static fn(string $s) => strtolower(trim($s)),
            [$name, $address->streetAddress, $address->postalCode, $address->city, $address->country],
        ));
    }
}
