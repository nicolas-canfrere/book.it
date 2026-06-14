<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Contract;

use App\Hotel\Application\Contract\HotelFinderInterface;
use App\Hotel\Application\Contract\HotelView;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class DoctrineHotelFinder implements HotelFinderInterface
{
    public function __construct(private HotelRepositoryInterface $hotelRepository)
    {
    }

    public function find(HotelId $hotelId): ?HotelView
    {
        $hotel = $this->hotelRepository->get($hotelId);

        if (null === $hotel) {
            return null;
        }

        return new HotelView(id: $hotel->id->value);
    }
}
