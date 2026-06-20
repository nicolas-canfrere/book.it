<?php

declare(strict_types=1);

namespace App\Geo\Application\UseCase\SearchGeoPlaces;

use App\Geo\Domain\GeoPlace;
use App\Geo\Domain\Port\GeoPlaceFinderInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class SearchGeoPlacesQueryHandler implements SyncQueryHandlerInterface
{
    private const int MAX_RESULTS = 10;

    public function __construct(private GeoPlaceFinderInterface $finder)
    {
    }

    /** @return list<GeoPlace> */
    public function __invoke(SearchGeoPlacesQuery $query): array
    {
        return $this->finder->search($query->query, self::MAX_RESULTS);
    }
}
