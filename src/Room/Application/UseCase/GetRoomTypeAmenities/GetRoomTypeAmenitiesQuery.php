<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoomTypeAmenities;

use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<list<string>> */
final readonly class GetRoomTypeAmenitiesQuery implements SyncQueryInterface
{
    public function __construct()
    {
    }
}
