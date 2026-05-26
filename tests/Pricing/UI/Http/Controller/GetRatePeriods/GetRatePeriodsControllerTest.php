<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\GetRatePeriods;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetRatePeriodsControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsEmptyListWhenNoPeriods(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'GET',
            uri: "/api/v1/rooms/{$roomId}/rate-periods",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{ratePeriods: array<mixed>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $body['ratePeriods']);
    }

    #[Test]
    public function itReturnsRatePeriodsSortedByCheckIn(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/v1/rooms/{$roomId}/rate-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-09-01', 'checkOut' => '2025-09-30', 'amount' => 200.00], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: "/api/v1/rooms/{$roomId}/rate-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-07-31', 'amount' => 150.00], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'GET',
            uri: "/api/v1/rooms/{$roomId}/rate-periods",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{ratePeriods: array<array{checkIn: string, checkOut: string, amountCents: int}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['ratePeriods']);
        self::assertSame('2025-07-01', $body['ratePeriods'][0]['checkIn']);
        self::assertSame('2025-09-01', $body['ratePeriods'][1]['checkIn']);
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
        $hotelId = $hotelBody['id'];

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
        /** @var array{id: string} $roomTypeBody */
        $roomTypeBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => $roomTypeBody['id']], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomBody */
        $roomBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $roomBody['id'];
    }
}
