<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\GetRoomType;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetRoomTypeControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = ['name' => 'Hotel Test', 'streetAddress' => '1 rue de la Paix', 'postalCode' => '75001', 'city' => 'Paris', 'country' => 'FR'];
    private const array ROOM_TYPE_PAYLOAD = ['name' => 'Single', 'livingSpaceCount' => 1, 'guestCapacity' => 1, 'isAccessible' => false, 'bedComposition' => [['type' => 'single', 'count' => 1]]];

    #[Test]
    public function itReturnsTheRoomType(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        /** @var array{id: string, name: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($roomTypeId, $body['id']);
        self::assertSame('Single', $body['name']);
    }

    #[Test]
    public function itReturns404WhenNotFound(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-types/00000000-0000-4000-8000-000000000000");

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
