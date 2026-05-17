<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\DeleteRatePeriod;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class DeleteRatePeriodControllerTest extends WebTestCase
{
    #[Test]
    public function itDeletesRatePeriodAndReturns204(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);
        $ratePeriodId = $this->createRatePeriod($client, $roomId, '2025-07-01', '2025-08-31', 150.00);

        $client->request(
            method: 'DELETE',
            uri: "/api/rooms/{$roomId}/rate-periods/{$ratePeriodId}",
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'GET',
            uri: "/api/rooms/{$roomId}/rate-periods",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{ratePeriods: array<mixed>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $body['ratePeriods']);
    }

    #[Test]
    public function itReturns404WhenRatePeriodNotFound(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'DELETE',
            uri: "/api/rooms/{$roomId}/rate-periods/00000000-0000-4000-8000-000000000001",
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

    private function createRatePeriod(KernelBrowser $client, string $roomId, string $checkIn, string $checkOut, float $amount): string
    {
        $client->request(
            'POST',
            "/api/rooms/{$roomId}/rate-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => $checkIn, 'checkOut' => $checkOut, 'amount' => $amount], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $body */
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
