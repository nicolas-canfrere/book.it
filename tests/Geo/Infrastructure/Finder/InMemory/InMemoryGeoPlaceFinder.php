<?php

declare(strict_types=1);

namespace App\Tests\Geo\Infrastructure\Finder\InMemory;

use App\Geo\Domain\GeoPlace;
use App\Geo\Domain\Port\GeoPlaceFinderInterface;

final class InMemoryGeoPlaceFinder implements GeoPlaceFinderInterface
{
    /** @var array{query: string, limit: int}|null */
    public ?array $lastCall = null;
    /** @var list<GeoPlace> */
    private array $places = [];

    public function addPlace(GeoPlace $place): void
    {
        $this->places[] = $place;
    }

    /** @return list<GeoPlace> */
    public function search(string $query, int $limit): array
    {
        $this->lastCall = ['query' => $query, 'limit' => $limit];

        return array_slice($this->places, 0, $limit);
    }
}
