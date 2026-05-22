<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Persistence\InMemory;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;

final class InMemoryHotelRepository implements HotelRepositoryInterface
{
    /** @var array<string, Hotel> */
    private array $hotels = [];

    public function add(Hotel $hotel): void
    {
        $this->hotels[$hotel->id] = $hotel;
    }

    public function save(Hotel $hotel): void
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

    public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null): HotelPage
    {
        $filtered = array_values(array_filter(
            $this->hotels,
            static fn(Hotel $h) => (null === $city || strtolower($h->address->city) === strtolower($city))
                && (null === $country || strtolower($h->address->country) === strtolower($country))
                && (null === $minStars || (null !== $h->starRating && $h->starRating->stars >= $minStars)),
        ));

        usort($filtered, static fn(Hotel $a, Hotel $b) => strcmp($a->name, $b->name));

        $total = count($filtered);
        $hotels = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new HotelPage($hotels, $total);
    }

    private function normalize(string $name, Address $address): string
    {
        return implode('|', array_map(
            static fn(string $s) => strtolower(trim($s)),
            [$name, $address->streetAddress, $address->postalCode, $address->city, $address->country],
        ));
    }
}
