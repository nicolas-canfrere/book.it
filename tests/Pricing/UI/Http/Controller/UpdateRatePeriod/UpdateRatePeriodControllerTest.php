<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\UpdateRatePeriod;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class UpdateRatePeriodControllerTest extends WebTestCase
{
    #[Test]
    public function itUpdatesRatePeriodAndReturns200(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);
        $ratePeriodId = $this->createRatePeriod($client, $roomId, '2025-07-01', '2025-08-31', 150.00);

        $client->request(
            method: 'PUT',
            uri: "/api/rooms/{$roomId}/rate-periods/{$ratePeriodId}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-09-01', 'amount' => 160.00], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{id: string, roomId: string, checkIn: string, checkOut: string, amountCents: int, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($ratePeriodId, $body['id']);
        self::assertSame($roomId, $body['roomId']);
        self::assertSame('2025-07-01', $body['checkIn']);
        self::assertSame('2025-09-01', $body['checkOut']);
        self::assertSame(16000, $body['amountCents']);
        self::assertGreaterThan(0, $body['createdAt']);
    }

    #[Test]
    public function itReturns404WhenRatePeriodNotFound(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'PUT',
            uri: "/api/rooms/{$roomId}/rate-periods/00000000-0000-4000-8000-000000000001",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-09-01', 'amount' => 160.00], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/rate-period-not-found', $body['type']);
        self::assertSame('Rate Period Not Found', $body['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    #[Test]
    public function itReturns409WhenUpdatedDatesOverlapAnotherPeriod(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $ratePeriodId = $this->createRatePeriod($client, $roomId, '2025-07-01', '2025-07-31', 150.00);
        $this->createRatePeriod($client, $roomId, '2025-09-01', '2025-09-30', 160.00);

        $client->request(
            method: 'PUT',
            uri: "/api/rooms/{$roomId}/rate-periods/{$ratePeriodId}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-09-15', 'amount' => 155.00], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/rate-period-overlap', $body['type']);
        self::assertSame('Rate Period Overlap', $body['title']);
        self::assertSame(Response::HTTP_CONFLICT, $body['status']);
    }

    #[Test]
    public function itReturns422WhenAmountIsNegative(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);
        $ratePeriodId = $this->createRatePeriod($client, $roomId, '2025-07-01', '2025-08-31', 150.00);

        $client->request(
            method: 'PUT',
            uri: "/api/rooms/{$roomId}/rate-periods/{$ratePeriodId}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-09-01', 'amount' => -10.0], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    private function createRatePeriod(KernelBrowser $client, string $roomId, string $checkIn, string $checkOut, float $amount): string
    {
        $client->request(
            'POST',
            "/api/rooms/{$roomId}/rate-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => $checkIn, 'checkOut' => $checkOut, 'amount' => $amount], \JSON_THROW_ON_ERROR),
        );
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
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
        $hotelId = $hotelBody['id'];

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomBody */
        $roomBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $roomBody['id'];
    }
}
