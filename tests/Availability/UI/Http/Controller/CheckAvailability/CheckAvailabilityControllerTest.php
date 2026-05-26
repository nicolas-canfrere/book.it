<?php

declare(strict_types=1);

namespace App\Tests\Availability\UI\Http\Controller\CheckAvailability;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class CheckAvailabilityControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsTrueWhenRoomIsAvailable(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request('GET', "/api/v1/rooms/{$roomId}/availability?checkIn=2025-06-10&checkOut=2025-06-13");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{available: bool} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($body['available']);
    }

    #[Test]
    public function itReturnsFalseWhenRoomIsBlocked(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/v1/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-06-10', 'checkOut' => '2025-06-15'], \JSON_THROW_ON_ERROR),
        );

        $client->request('GET', "/api/v1/rooms/{$roomId}/availability?checkIn=2025-06-12&checkOut=2025-06-17");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{available: bool} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertFalse($body['available']);
    }

    #[Test]
    public function itReturns422WhenCheckInIsMissing(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request('GET', "/api/v1/rooms/{$roomId}/availability?checkOut=2025-06-13");

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenDateFormatIsInvalid(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request('GET', "/api/v1/rooms/{$roomId}/availability?checkIn=not-a-date&checkOut=2025-06-13");

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404WhenRoomIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/rooms/not-a-uuid/availability?checkIn=2025-06-10&checkOut=2025-06-13');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerRoomAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Hotel Test',
                'streetAddress' => '1 rue de la Paix',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $hotelBody */
        $hotelBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelBody['id']}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Single',
                'livingSpaceCount' => 1,
                'guestCapacity' => 1,
                'isAccessible' => false,
                'bedComposition' => [['type' => 'single', 'count' => 1]],
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomTypeBody */
        $roomTypeBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelBody['id']}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => $roomTypeBody['id']], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomBody */
        $roomBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $roomBody['id'];
    }
}
