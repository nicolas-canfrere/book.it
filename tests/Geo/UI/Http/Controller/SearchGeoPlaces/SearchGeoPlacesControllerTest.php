<?php

declare(strict_types=1);

namespace App\Tests\Geo\UI\Http\Controller\SearchGeoPlaces;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class SearchGeoPlacesControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsPlacesMatchingTheFuzzyQuery(): void
    {
        $client = static::createClient();
        /** @var Connection $geoConnection */
        $geoConnection = static::getContainer()->get('doctrine.dbal.geo_connection');
        $geoConnection->executeStatement('TRUNCATE geo_place');
        $geoConnection->executeStatement(
            "INSERT INTO geo_place (geoname_id, name, ascii_name, country_code, admin1_code) VALUES
                (2988507, 'Paris', 'Paris', 'FR', '11'),
                (4717560, 'Paris', 'Paris', 'US', 'TX'),
                (2950159, 'Berlin', 'Berlin', 'DE', NULL)",
        );

        $client->request(method: 'GET', uri: '/api/v1/geo/places?query=pari');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{id: int, name: string, countryCode: string, admin1Code: string|null}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $ids = array_column($body['data'], 'id');
        self::assertContains(2988507, $ids);
        self::assertContains(4717560, $ids);
        self::assertNotContains(2950159, $ids);
    }

    #[Test]
    public function itReturns422WhenQueryIsTooShort(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/api/v1/geo/places?query=p');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }
}
