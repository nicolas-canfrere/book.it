<?php

declare(strict_types=1);

namespace App\Geo\Application\UseCase\SearchGeoPlaces;

use App\Geo\Domain\GeoPlace;
use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<list<GeoPlace>> */
final readonly class SearchGeoPlacesQuery implements SyncQueryInterface
{
    public function __construct(public string $query)
    {
    }
}
