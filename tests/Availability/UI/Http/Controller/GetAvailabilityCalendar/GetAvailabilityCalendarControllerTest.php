<?php

declare(strict_types=1);

namespace App\Tests\Availability\UI\Http\Controller\GetAvailabilityCalendar;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetAvailabilityCalendarControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsBlockedPeriodsOrderedByCheckIn(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request('POST', "/api/rooms/{$roomId}/blocked-periods", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['checkIn' => '2025-06-15', 'checkOut' => '2025-06-18'], \JSON_THROW_ON_ERROR));
        $client->request('POST', "/api/rooms/{$roomId}/blocked-periods", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['checkIn' => '2025-06-10', 'checkOut' => '2025-06-13'], \JSON_THROW_ON_ERROR));

        $client->request('GET', "/api/rooms/{$roomId}/blocked-periods");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{blockedPeriods: list<array{id: string, roomId: string, checkIn: string, checkOut: string, createdAt: int}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['blockedPeriods']);
        self::assertSame('2025-06-10', $body['blockedPeriods'][0]['checkIn']);
        self::assertSame('2025-06-15', $body['blockedPeriods'][1]['checkIn']);
        self::assertSame($roomId, $body['blockedPeriods'][0]['roomId']);
    }

    #[Test]
    public function itReturnsEmptyListWhenNoBlocks(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request('GET', "/api/rooms/{$roomId}/blocked-periods");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{blockedPeriods: list<mixed>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $body['blockedPeriods']);
    }

    #[Test]
    public function itReturns404WhenRoomIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/rooms/not-a-uuid/blocked-periods');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerRoomAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
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
            uri: "/api/hotels/{$hotelBody['id']}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomBody */
        $roomBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $roomBody['id'];
    }
}
