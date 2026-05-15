<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Room\Domain\Port\HotelExistsInterface;

final readonly class HotelExistenceChecker implements HotelExistsInterface
{
    public function __construct(private HotelRepositoryInterface $hotelRepository)
    {
    }

    public function exists(string $hotelId): bool
    {
        $hotel = $this->hotelRepository->get($hotelId);

        return null !== $hotel;
    }
}
