<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\UpdatePromotion;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class UpdatePromotionControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itUpdatesPromotionAndReturns200(): void
    {
        $client = static::createAuthenticatedClient();
        $roomId = $this->registerRoomAndGetId($client);
        $promotionId = $this->createPromotion($client, $roomId, '2025-07-01', '2025-08-31', 20);

        $client->request(
            method: 'PUT',
            uri: "/api/v1/rooms/{$roomId}/promotions/{$promotionId}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-09-01', 'discountPercent' => 25], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{id: string, roomId: string, checkIn: string, checkOut: string, discountPercent: int, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($promotionId, $body['id']);
        self::assertSame($roomId, $body['roomId']);
        self::assertSame('2025-07-01', $body['checkIn']);
        self::assertSame('2025-09-01', $body['checkOut']);
        self::assertSame(25, $body['discountPercent']);
        self::assertGreaterThan(0, $body['createdAt']);
    }

    #[Test]
    public function itReturns404WhenPromotionNotFound(): void
    {
        $client = static::createAuthenticatedClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'PUT',
            uri: "/api/v1/rooms/{$roomId}/promotions/00000000-0000-4000-8000-000000000001",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-09-01', 'discountPercent' => 25], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/promotion-not-found', $body['type']);
        self::assertSame('Promotion Not Found', $body['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    #[Test]
    public function itReturns409WhenUpdatedDatesOverlapAnotherPromotion(): void
    {
        $client = static::createAuthenticatedClient();
        $roomId = $this->registerRoomAndGetId($client);

        $promotionId = $this->createPromotion($client, $roomId, '2025-07-01', '2025-07-31', 20);
        $this->createPromotion($client, $roomId, '2025-09-01', '2025-09-30', 15);

        $client->request(
            method: 'PUT',
            uri: "/api/v1/rooms/{$roomId}/promotions/{$promotionId}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-09-15', 'discountPercent' => 20], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/promotion-overlap', $body['type']);
        self::assertSame('Promotion Overlap', $body['title']);
        self::assertSame(Response::HTTP_CONFLICT, $body['status']);
    }

    private function createPromotion(KernelBrowser $client, string $roomId, string $checkIn, string $checkOut, int $discountPercent): string
    {
        $client->request(
            'POST',
            "/api/v1/rooms/{$roomId}/promotions",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => $checkIn, 'checkOut' => $checkOut, 'discountPercent' => $discountPercent], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
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
