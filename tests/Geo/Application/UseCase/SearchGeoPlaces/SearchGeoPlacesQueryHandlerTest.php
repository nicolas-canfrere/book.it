<?php

declare(strict_types=1);

namespace App\Tests\Geo\Application\UseCase\SearchGeoPlaces;

use App\Geo\Application\UseCase\SearchGeoPlaces\SearchGeoPlacesQuery;
use App\Geo\Application\UseCase\SearchGeoPlaces\SearchGeoPlacesQueryHandler;
use App\Geo\Domain\GeoPlace;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use App\Tests\Geo\Infrastructure\Finder\InMemory\InMemoryGeoPlaceFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SearchGeoPlacesQueryHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsPlacesFromTheFinder(): void
    {
        $finder = new InMemoryGeoPlaceFinder();
        $finder->addPlace(new GeoPlace(id: new GeoPlaceId('2988507'), name: 'Paris', countryCode: 'FR', admin1Code: '11'));
        $handler = new SearchGeoPlacesQueryHandler($finder);

        $result = $handler(new SearchGeoPlacesQuery('pari'));

        self::assertCount(1, $result);
        self::assertSame('2988507', $result[0]->id->value);
    }

    #[Test]
    public function itDelegatesToTheFinderWithAMaxLimitOfTen(): void
    {
        $finder = new InMemoryGeoPlaceFinder();
        $handler = new SearchGeoPlacesQueryHandler($finder);

        $handler(new SearchGeoPlacesQuery('pari'));

        self::assertSame(['query' => 'pari', 'limit' => 10], $finder->lastCall);
    }
}
