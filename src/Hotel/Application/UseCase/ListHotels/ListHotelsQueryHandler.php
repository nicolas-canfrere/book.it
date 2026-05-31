<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ListHotels;

use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListHotelsQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
    ) {
    }

    public function __invoke(ListHotelsQuery $query): HotelPage
    {
        return $this->hotelRepository->list(
            $query->page,
            $query->limit,
            $query->city,
            $query->country,
            $query->minStars,
            $query->amenities,
        );
    }
}
