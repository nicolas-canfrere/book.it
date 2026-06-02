<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoomTypeAmenities;

use App\Room\Domain\ValueObject\RoomAmenity;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetRoomTypeAmenitiesQueryHandler implements SyncQueryHandlerInterface
{
    /** @return string[] */
    public function __invoke(GetRoomTypeAmenitiesQuery $query): array
    {
        return RoomAmenity::values();
    }
}
