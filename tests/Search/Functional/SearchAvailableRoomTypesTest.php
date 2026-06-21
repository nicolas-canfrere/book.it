<?php

declare(strict_types=1);

namespace App\Tests\Search\Functional;

use App\Tests\Shared\AuthenticatedWebTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[Group('functional')]
final class SearchAvailableRoomTypesTest extends AuthenticatedWebTestCase
{
    private const HOTEL_ID = '77777777-7777-7777-7777-777777777777';
    private const ROOM_TYPE_ID = '88888888-8888-8888-8888-888888888888';
    private const ROOM_ID = '99999999-9999-9999-9999-999999999999';
    private const GEO_PLACE_ID = '2988507';

    private KernelBrowser $client;
    private Connection $searchConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createAuthenticatedClient();
        $this->searchConnection = static::getContainer()->get('doctrine.dbal.search_connection');
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    #[Test]
    public function itReturns200WithEmptyResultsWhenNothingMatches(): void
    {
        $this->client->request('GET', '/api/v1/search?geoPlaceId=0000000&city=Nowhere&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(200);
        self::assertJson((string) $this->client->getResponse()->getContent());

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertSame([], $body);
    }

    #[Test]
    public function itReturns422WhenGeoPlaceIdIsMissing(): void
    {
        $this->client->request('GET', '/api/v1/search?city=Paris&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns422WhenCityIsMissing(): void
    {
        $this->client->request('GET', '/api/v1/search?geoPlaceId=2988507&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns422WhenGuestsIsZero(): void
    {
        $this->client->request('GET', '/api/v1/search?geoPlaceId=2988507&city=Paris&checkIn=2026-07-01&checkOut=2026-07-05&guests=0');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns422WhenCheckOutIsBeforeCheckIn(): void
    {
        $this->client->request('GET', '/api/v1/search?geoPlaceId=2988507&city=Paris&checkIn=2026-07-05&checkOut=2026-07-01&guests=2');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns422WhenCheckOutEqualsCheckIn(): void
    {
        $this->client->request('GET', '/api/v1/search?geoPlaceId=2988507&city=Paris&checkIn=2026-07-01&checkOut=2026-07-01&guests=2');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturnsResultsFilteredByGeoPlaceIdRegardlessOfCityText(): void
    {
        $this->insertFixtures();

        $this->client->request('GET', '/api/v1/search?geoPlaceId=' . self::GEO_PLACE_ID . '&city=ThisTextIsIgnored&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertCount(1, $body);
        $result = $body[0];
        self::assertIsArray($result);
        self::assertSame(self::ROOM_TYPE_ID, $result['roomTypeId']);
        self::assertSame(self::GEO_PLACE_ID, $result['geoPlaceId']);
    }

    #[Test]
    public function itReturnsNoResultsWhenGeoPlaceIdDoesNotMatchEvenIfCityMatches(): void
    {
        $this->insertFixtures();

        $this->client->request('GET', '/api/v1/search?geoPlaceId=9999999&city=Paris&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame([], $body);
    }

    private function insertFixtures(): void
    {
        $this->searchConnection->executeStatement(
            'INSERT INTO hotel_room_types (room_type_id, hotel_id, hotel_name, city, country, geo_place_id, hotel_amenities, room_type_name, guest_capacity, bed_composition, room_amenities)
             VALUES (:roomTypeId, :hotelId, :hotelName, :city, :country, :geoPlaceId, :hotelAmenities, :roomTypeName, :guestCapacity, :bedComposition, :roomAmenities)',
            [
                'roomTypeId' => self::ROOM_TYPE_ID,
                'hotelId' => self::HOTEL_ID,
                'hotelName' => 'Grand Hôtel du Louvre',
                'city' => 'Paris',
                'country' => 'FR',
                'geoPlaceId' => self::GEO_PLACE_ID,
                'hotelAmenities' => '[]',
                'roomTypeName' => 'Deluxe Double',
                'guestCapacity' => 2,
                'bedComposition' => '{"double":1}',
                'roomAmenities' => '[]',
            ],
        );

        $this->searchConnection->executeStatement(
            'INSERT INTO room_index (room_id, room_type_id, hotel_id) VALUES (:roomId, :roomTypeId, :hotelId)',
            [
                'roomId' => self::ROOM_ID,
                'roomTypeId' => self::ROOM_TYPE_ID,
                'hotelId' => self::HOTEL_ID,
            ],
        );
    }

    private function cleanUp(): void
    {
        $this->searchConnection->executeStatement('DELETE FROM unavailable_periods');
        $this->searchConnection->executeStatement('DELETE FROM room_index');
        $this->searchConnection->executeStatement('DELETE FROM hotel_room_types');
    }
}
