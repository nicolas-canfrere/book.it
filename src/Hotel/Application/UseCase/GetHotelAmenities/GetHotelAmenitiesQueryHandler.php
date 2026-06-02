<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\GetHotelAmenities;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetHotelAmenitiesQueryHandler implements SyncQueryHandlerInterface
{
    /** @return string[] */
    public function __invoke(GetHotelAmenitiesQuery $query): array
    {
        return HotelAmenity::values();
    }
}
