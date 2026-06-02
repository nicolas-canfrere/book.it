<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\GetHotelAmenities;

use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<list<string>> */
final readonly class GetHotelAmenitiesQuery implements SyncQueryInterface
{
    public function __construct()
    {
    }
}
