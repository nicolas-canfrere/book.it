<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\DeleteRoomType;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class DeleteRoomTypeControllerTest extends AuthenticatedWebTestCase
{
    private const array HOTEL_PAYLOAD = ['name' => 'Hotel Test', 'streetAddress' => '1 rue de la Paix', 'postalCode' => '75001', 'city' => 'Paris', 'country' => 'FR'];
    private const array ROOM_TYPE_PAYLOAD = ['name' => 'Single', 'livingSpaceCount' => 1, 'guestCapacity' => 1, 'isAccessible' => false, 'bedComposition' => [['type' => 'single', 'count' => 1]]];

    #[Test]
    public function itDeletesAndReturns204(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request('DELETE', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}");

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}");
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns409WhenRoomTypeHasRooms(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => $roomTypeId], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request('DELETE', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-type-has-rooms', $body['type']);
        self::assertSame('Room Type Has Rooms', $body['title']);
        self::assertSame(Response::HTTP_CONFLICT, $body['status']);
    }

    #[Test]
    public function itReturns404WhenNotFound(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request('DELETE', "/api/v1/hotels/{$hotelId}/room-types/00000000-0000-4000-8000-000000000000");

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function registerRoomTypeAndGetId(KernelBrowser $client, string $hotelId): string
    {
        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::ROOM_TYPE_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
