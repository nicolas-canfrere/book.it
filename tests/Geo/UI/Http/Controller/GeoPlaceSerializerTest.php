<?php

declare(strict_types=1);

namespace App\Tests\Geo\UI\Http\Controller;

use App\Geo\Domain\GeoPlace;
use App\Geo\UI\Http\Controller\GeoPlaceSerializer;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GeoPlaceSerializerTest extends TestCase
{
    #[Test]
    public function itSerializesAGeoPlaceWithAnIntegerId(): void
    {
        $geoPlace = new GeoPlace(id: new GeoPlaceId('2988507'), name: 'Paris', countryCode: 'FR', admin1Code: '11');

        $result = (new GeoPlaceSerializer())->serialize($geoPlace);

        self::assertSame([
            'id' => 2988507,
            'name' => 'Paris',
            'countryCode' => 'FR',
            'admin1Code' => '11',
        ], $result);
    }

    #[Test]
    public function itSerializesANullAdmin1Code(): void
    {
        $geoPlace = new GeoPlace(id: new GeoPlaceId('2950159'), name: 'Berlin', countryCode: 'DE', admin1Code: null);

        $result = (new GeoPlaceSerializer())->serialize($geoPlace);

        self::assertNull($result['admin1Code']);
    }
}
