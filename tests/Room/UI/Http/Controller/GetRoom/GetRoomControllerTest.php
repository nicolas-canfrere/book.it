<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\GetRoom;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetRoomControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel Get Room Test',
        'streetAddress' => '2 rue de la Paix',
        'postalCode' => '75002',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    #[Test]
    public function itReturns200WithCorrectRoomShape(): void
    {
        $client = static::createClient();
        ['hotelId' => $hotelId, 'roomId' => $roomId] = $this->registerRoomAndGetIds($client);

        $client->request('GET', "/api/v1/rooms/{$roomId}");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{id: string, hotelId: string, number: string, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($roomId, $body['id']);
        self::assertSame($hotelId, $body['hotelId']);
        self::assertSame('101', $body['number']);
        self::assertGreaterThan(0, $body['createdAt']);
    }

    #[Test]
    public function itReturns404WhenRoomDoesNotExist(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/rooms/00000000-0000-4000-8000-000000000000');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns404WhenIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/rooms/not-a-uuid');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    /** @return array{hotelId: string, roomId: string} */
    private function registerRoomAndGetIds(KernelBrowser $client): array
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $hotel */
        $hotel = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $hotelId = $hotel['id'];

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

        /** @var array{id: string} $roomType */
        $roomType = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => $roomType['id']], \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $room */
        $room = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return ['hotelId' => $hotelId, 'roomId' => $room['id']];
    }
}
