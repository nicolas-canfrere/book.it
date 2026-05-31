<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\DeclareRoomTypeAmenities;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class DeclareRoomTypeAmenitiesControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel Test',
        'streetAddress' => '1 rue de la Paix',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];
    private const array ROOM_TYPE_PAYLOAD = [
        'name' => 'Standard',
        'livingSpaceCount' => 1,
        'guestCapacity' => 2,
        'isAccessible' => false,
        'bedComposition' => [['type' => 'double', 'count' => 1]],
    ];

    #[Test]
    public function itReturns204WithValidAmenities(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            'PATCH',
            "/api/v1/room-types/{$roomTypeId}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['wifi', 'tv', 'minibar']], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns204WithEmptyList(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            'PATCH',
            "/api/v1/room-types/{$roomTypeId}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => []], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404ForUnknownRoomType(): void
    {
        $client = static::createClient();

        $client->request(
            'PATCH',
            '/api/v1/room-types/00000000-0000-4000-8000-000000000000/amenities',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['wifi']], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422ForUnknownAmenityValue(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            'PATCH',
            "/api/v1/room-types/{$roomTypeId}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['not_a_real_amenity']], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422ForDuplicateAmenityValue(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            'PATCH',
            "/api/v1/room-types/{$roomTypeId}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['wifi', 'wifi']], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request(
            'POST',
            '/api/v1/hotels',
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
            'POST',
            "/api/v1/hotels/{$hotelId}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::ROOM_TYPE_PAYLOAD, \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
