<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\GetBaseRate;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetBaseRateControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsBaseRate(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'PUT',
            uri: "/api/rooms/{$roomId}/base-rate",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amount' => 120.00], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'GET',
            uri: "/api/rooms/{$roomId}/base-rate",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{roomId: string, amountCents: int, updatedAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($roomId, $body['roomId']);
        self::assertSame(12000, $body['amountCents']);
        self::assertGreaterThan(0, $body['updatedAt']);
    }

    #[Test]
    public function itReturns404WhenRoomDoesNotExist(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'GET',
            uri: '/api/rooms/00000000-0000-4000-8000-000000000000/base-rate',
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-not-found', $body['type']);
        self::assertSame('Room Not Found', $body['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    #[Test]
    public function itReturns404WhenNoBaseRateConfigured(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'GET',
            uri: "/api/rooms/{$roomId}/base-rate",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/base-rate-not-found', $body['type']);
        self::assertSame('Base Rate Not Found', $body['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
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
