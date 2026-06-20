<?php

declare(strict_types=1);

namespace App\Tests\Geo\Infrastructure\Finder;

use App\Geo\Infrastructure\Finder\DbalGeoPlaceFinder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DbalGeoPlaceFinderTest extends TestCase
{
    #[Test]
    public function itHydratesRowsIntoGeoPlaces(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->stringContains('FROM geo_place'),
                ['query' => 'pari', 'limit' => 10],
            )
            ->willReturn([
                ['geoname_id' => '2988507', 'name' => 'Paris', 'country_code' => 'FR', 'admin1_code' => '11'],
                ['geoname_id' => '4717560', 'name' => 'Paris', 'country_code' => 'US', 'admin1_code' => 'TX'],
            ]);

        $results = (new DbalGeoPlaceFinder($connection))->search('pari', 10);

        self::assertCount(2, $results);
        self::assertSame('2988507', $results[0]->id->value);
        self::assertSame('Paris', $results[0]->name);
        self::assertSame('FR', $results[0]->countryCode);
        self::assertSame('11', $results[0]->admin1Code);
        self::assertSame('4717560', $results[1]->id->value);
        self::assertSame('TX', $results[1]->admin1Code);
    }

    #[Test]
    public function itHydratesNullAdmin1Code(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['geoname_id' => '2950159', 'name' => 'Berlin', 'country_code' => 'DE', 'admin1_code' => null],
        ]);

        $results = (new DbalGeoPlaceFinder($connection))->search('berl', 10);

        self::assertNull($results[0]->admin1Code);
    }
}
