<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\ListRoomTypesByAmenity;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class ListRoomTypesByAmenityControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel Filtre',
        'streetAddress' => '5 rue des Lilas',
        'postalCode' => '69001',
        'city' => 'Lyon',
        'country' => 'FR',
    ];

    #[Test]
    public function itReturnsAllRoomTypesWhenNoAmenityFilterGiven(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Suite', ['wifi', 'balcony']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Standard', ['wifi']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Basic', []);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-type-catalogue");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{name: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(3, $body['meta']['total']);
    }

    #[Test]
    public function itFiltersByASingleAmenityAndReturnsSortedResults(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Suite', ['wifi', 'balcony']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Standard', ['wifi']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Basic', []);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-type-catalogue?amenities[]=wifi");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{name: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(2, $body['meta']['total']);
        self::assertSame('Standard', $body['data'][0]['name']);
        self::assertSame('Suite', $body['data'][1]['name']);
    }

    #[Test]
    public function itFiltersByMultipleAmenitiesWithAndLogic(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Suite', ['wifi', 'balcony']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Standard', ['wifi']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Basic', []);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-type-catalogue?amenities[]=wifi&amenities[]=balcony");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{name: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('Suite', $body['data'][0]['name']);
    }

    #[Test]
    public function itRejects422ForAnInvalidAmenityValue(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-type-catalogue?amenities[]=not_a_real_amenity");

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    private function registerHotel(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    /** @param string[] $amenities */
    private function registerRoomTypeWithAmenities(KernelBrowser $client, string $hotelId, string $name, array $amenities): string
    {
        $payload = ['name' => $name, 'livingSpaceCount' => 1, 'guestCapacity' => 2, 'isAccessible' => false, 'bedComposition' => [['type' => 'double', 'count' => 1]]];
        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $rt */
        $rt = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $roomTypeId = $rt['id'];

        if ([] !== $amenities) {
            $client->request('PATCH', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}/amenities", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['amenities' => $amenities], \JSON_THROW_ON_ERROR));
        }

        return $roomTypeId;
    }
}
