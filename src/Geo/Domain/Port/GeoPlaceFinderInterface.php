<?php

declare(strict_types=1);

namespace App\Geo\Domain\Port;

use App\Geo\Domain\GeoPlace;

interface GeoPlaceFinderInterface
{
    /** @return list<GeoPlace> */
    public function search(string $query, int $limit): array;
}
