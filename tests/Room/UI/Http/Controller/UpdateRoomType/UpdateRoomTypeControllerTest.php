<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\UpdateRoomType;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class UpdateRoomTypeControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = ['name' => 'Hotel Test', 'streetAddress' => '1 rue de la Paix', 'postalCode' => '75001', 'city' => 'Paris', 'country' => 'FR'];
    private const array INITIAL_PAYLOAD = ['name' => 'Single', 'livingSpaceCount' => 1, 'guestCapacity' => 1, 'isAccessible' => false, 'bedComposition' => [['type' => 'single', 'count' => 1]]];

    #[Test]
    public function itUpdatesAndReturns200(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId, self::INITIAL_PAYLOAD);

        $updatePayload = ['name' => 'Double', 'livingSpaceCount' => 1, 'surfaceM2' => 25, 'guestCapacity' => 2, 'isAccessible' => true, 'bedComposition' => [['type' => 'double', 'count' => 1]]];

        $client->request('PUT', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($updatePayload, \JSON_THROW_ON_ERROR));

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        /** @var array{name: string, surfaceM2: int, guestCapacity: int, isAccessible: bool} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Double', $body['name']);
        self::assertSame(25, $body['surfaceM2']);
        self::assertSame(2, $body['guestCapacity']);
        self::assertTrue($body['isAccessible']);
    }

    #[Test]
    public function itReturns404WhenNotFound(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request('PUT', "/api/v1/hotels/{$hotelId}/room-types/00000000-0000-4000-8000-000000000000", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::INITIAL_PAYLOAD, \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns409WhenNewNameAlreadyTaken(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId, self::INITIAL_PAYLOAD);
        $this->registerRoomTypeAndGetId($client, $hotelId, array_merge(self::INITIAL_PAYLOAD, ['name' => 'Double']));

        $client->request('PUT', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(array_merge(self::INITIAL_PAYLOAD, ['name' => 'Double']), \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function registerRoomTypeAndGetId(KernelBrowser $client, string $hotelId, array $payload): string
    {
        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
