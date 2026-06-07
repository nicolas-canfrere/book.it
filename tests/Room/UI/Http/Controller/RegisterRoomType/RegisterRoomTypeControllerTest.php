<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\RegisterRoomType;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class RegisterRoomTypeControllerTest extends AuthenticatedWebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel Test',
        'streetAddress' => '1 rue de la Paix',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    private const array VALID_PAYLOAD = [
        'name' => 'Suite Royale',
        'livingSpaceCount' => 2,
        'surfaceM2' => 80,
        'guestCapacity' => 2,
        'isAccessible' => false,
        'bedComposition' => [['type' => 'king', 'count' => 1]],
    ];

    #[Test]
    public function itRegistersARoomTypeAndReturns201(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, hotelId: string, name: string, livingSpaceCount: int, surfaceM2: int|null, guestCapacity: int, isAccessible: bool, bedComposition: list<array{type: string, count: int}>, amenities: list<string>, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame($hotelId, $body['hotelId']);
        self::assertSame('Suite Royale', $body['name']);
        self::assertSame(2, $body['livingSpaceCount']);
        self::assertSame(80, $body['surfaceM2']);
        self::assertSame(2, $body['guestCapacity']);
        self::assertFalse($body['isAccessible']);
        self::assertSame([['type' => 'king', 'count' => 1]], $body['bedComposition']);
    }

    #[Test]
    public function itAcceptsNullSurfaceM2(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $payload = self::VALID_PAYLOAD;
        unset($payload['surfaceM2']);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        /** @var array{surfaceM2: int|null} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNull($body['surfaceM2']);
    }

    #[Test]
    public function itReturns409WhenNameAlreadyExistsInHotel(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR));

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-type-already-exists', $body['type']);
    }

    #[Test]
    public function itReturns404WhenHotelDoesNotExist(): void
    {
        $client = static::createAuthenticatedClient();
        $client->request('POST', '/api/v1/hotels/00000000-0000-4000-8000-000000000000/room-types', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenNameIsMissing(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $payload = self::VALID_PAYLOAD;
        unset($payload['name']);

        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenBedCompositionIsEmpty(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $payload = array_merge(self::VALID_PAYLOAD, ['bedComposition' => []]);

        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenBedTypeIsInvalid(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $payload = array_merge(self::VALID_PAYLOAD, ['bedComposition' => [['type' => 'hammock', 'count' => 1]]]);

        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
