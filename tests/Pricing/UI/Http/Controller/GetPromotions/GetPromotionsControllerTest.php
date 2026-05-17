<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\GetPromotions;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetPromotionsControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsEmptyListWhenNoPromotions(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'GET',
            uri: "/api/rooms/{$roomId}/promotions",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{promotions: array<mixed>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $body['promotions']);
    }

    #[Test]
    public function itReturnsPromotionsSortedByCheckIn(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $this->createPromotion($client, $roomId, '2025-09-01', '2025-09-30', 15);
        $this->createPromotion($client, $roomId, '2025-07-01', '2025-07-31', 20);

        $client->request(
            method: 'GET',
            uri: "/api/rooms/{$roomId}/promotions",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{promotions: array<array{checkIn: string, checkOut: string, discountPercent: int}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['promotions']);
        self::assertSame('2025-07-01', $body['promotions'][0]['checkIn']);
        self::assertSame('2025-09-01', $body['promotions'][1]['checkIn']);
    }

    private function createPromotion(KernelBrowser $client, string $roomId, string $checkIn, string $checkOut, int $discountPercent): string
    {
        $client->request(
            'POST',
            "/api/rooms/{$roomId}/promotions",
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
