<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;

interface HotelRepositoryInterface
{
    public function add(Hotel $hotel): void;

    public function save(Hotel $hotel): void;

    public function get(string $id): ?Hotel;

    public function existsByNameAndAddress(string $name, Address $address): bool;

    public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null): HotelPage;
}
