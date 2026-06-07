<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Room\Domain\Model\RoomTypePage;

interface RoomTypeCatalogueFinderInterface
{
    /** @param string[] $amenities */
    public function find(string $hotelId, array $amenities, int $page, int $limit): RoomTypePage;
}
