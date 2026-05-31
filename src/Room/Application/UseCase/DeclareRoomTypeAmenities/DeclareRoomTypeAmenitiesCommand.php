<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\DeclareRoomTypeAmenities;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeclareRoomTypeAmenitiesCommand implements SyncCommandInterface
{
    /**
     * @param string[] $amenities
     */
    public function __construct(
        public string $roomTypeId,
        public array $amenities,
    ) {
    }
}
