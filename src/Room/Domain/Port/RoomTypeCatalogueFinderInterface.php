<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Room\Domain\Model\RoomTypePage;
use App\Shared\Domain\ValueObject\HotelId;

interface RoomTypeCatalogueFinderInterface
{
    /** @param string[] $amenities */
    public function find(HotelId $hotelId, array $amenities, int $page, int $limit): RoomTypePage;
}
