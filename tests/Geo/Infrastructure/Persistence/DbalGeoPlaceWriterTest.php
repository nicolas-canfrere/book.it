<?php

declare(strict_types=1);

namespace App\Tests\Geo\Infrastructure\Persistence;

use App\Geo\Infrastructure\Persistence\DbalGeoPlaceWriter;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DbalGeoPlaceWriterTest extends TestCase
{
    #[Test]
    public function itUpsertsAGeoPlace(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('ON CONFLICT (geoname_id) DO UPDATE'),
                [
                    'geonameId' => '2988507',
                    'name' => 'Paris',
                    'asciiName' => 'Paris',
                    'countryCode' => 'FR',
                    'admin1Code' => '11',
                ],
            );

        (new DbalGeoPlaceWriter($connection))->upsert(new GeoPlaceId('2988507'), 'Paris', 'Paris', 'FR', '11');
    }
}
