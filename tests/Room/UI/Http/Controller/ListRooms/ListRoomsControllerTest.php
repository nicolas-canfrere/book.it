<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\ListRooms;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class ListRoomsControllerTest extends AuthenticatedWebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel List Rooms Test',
        'streetAddress' => '3 rue du Catalogue',
        'postalCode' => '75003',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    #[Test]
    public function itReturnsEmptyCatalogueForHotelWithNoRooms(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/rooms");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<mixed>, meta: array{page: int, limit: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(0, $body['data']);
        self::assertSame(0, $body['meta']['total']);
        self::assertSame(1, $body['meta']['page']);
        self::assertSame(20, $body['meta']['limit']);
    }

    #[Test]
    public function itReturnsRoomsSortedByNumberAscending(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);
        $this->registerRoom($client, $hotelId, $roomTypeId, '202');
        $this->registerRoom($client, $hotelId, $roomTypeId, '101');

        $client->request('GET', "/api/v1/hotels/{$hotelId}/rooms");

        /** @var array{data: list<array{number: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['data']);
        self::assertSame('101', $body['data'][0]['number']);
        self::assertSame('202', $body['data'][1]['number']);
    }

    #[Test]
    public function itIncludesBaseRateAmountCentsWhenSet(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);
        $roomId = $this->registerRoomAndGetId($client, $hotelId, $roomTypeId, '101');

        $client->request(
            method: 'PUT',
            uri: "/api/v1/rooms/{$roomId}/base-rate",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amount' => 120.00], \JSON_THROW_ON_ERROR),
        );

        $client->request('GET', "/api/v1/hotels/{$hotelId}/rooms");

        /** @var array{data: list<array{baseRateAmountCents: ?int}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(12000, $body['data'][0]['baseRateAmountCents']);
    }

    #[Test]
    public function itReturnsNullBaseRateAmountCentsWhenNotSet(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);
        $this->registerRoom($client, $hotelId, $roomTypeId, '101');

        $client->request('GET', "/api/v1/hotels/{$hotelId}/rooms");

        /** @var array{data: list<array{baseRateAmountCents: ?int}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNull($body['data'][0]['baseRateAmountCents']);
    }

    #[Test]
    public function itReturnsPaginatedResults(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);
        for ($i = 1; $i <= 5; ++$i) {
            $this->registerRoom($client, $hotelId, $roomTypeId, sprintf('%03d', $i));
        }

        $client->request('GET', "/api/v1/hotels/{$hotelId}/rooms?page=2&limit=2");

        /** @var array{data: list<mixed>, meta: array{page: int, limit: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['data']);
        self::assertSame(5, $body['meta']['total']);
        self::assertSame(2, $body['meta']['page']);
        self::assertSame(2, $body['meta']['limit']);
        self::assertSame(3, $body['meta']['totalPages']);
    }

    #[Test]
    public function itReturns422WhenPageIsInvalid(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/rooms?page=0");

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404WhenHotelIdIsNotAValidUuidV4(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/hotels/not-a-uuid/rooms');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function registerRoomTypeAndGetId(KernelBrowser $client, string $hotelId): string
    {
        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Single',
                'livingSpaceCount' => 1,
                'guestCapacity' => 1,
                'isAccessible' => false,
                'bedComposition' => [['type' => 'single', 'count' => 1]],
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function registerRoom(KernelBrowser $client, string $hotelId, string $roomTypeId, string $number): void
    {
        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => $number, 'floor' => 1, 'roomTypeId' => $roomTypeId], \JSON_THROW_ON_ERROR),
        );
    }

    private function registerRoomAndGetId(KernelBrowser $client, string $hotelId, string $roomTypeId, string $number): string
    {
        $this->registerRoom($client, $hotelId, $roomTypeId, $number);

        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
