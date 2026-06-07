<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\GetPricingQuote;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetPricingQuoteControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itReturnsQuoteWithCorrectTotalWhenBaseRateExistsAndNoPromotions(): void
    {
        $client = static::createAuthenticatedClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'PUT',
            uri: "/api/v1/rooms/{$roomId}/base-rate",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amount' => 100.00], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'GET',
            uri: "/api/v1/rooms/{$roomId}/pricing-quote?checkIn=2025-07-01&checkOut=2025-07-04",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{roomId: string, checkIn: string, checkOut: string, totalAmountCents: int, nights: array<array{date: string, amountCents: int, discountPercent: int|null, effectiveAmountCents: int}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($roomId, $body['roomId']);
        self::assertSame('2025-07-01', $body['checkIn']);
        self::assertSame('2025-07-04', $body['checkOut']);
        self::assertSame(30000, $body['totalAmountCents']);
        self::assertCount(3, $body['nights']);
        self::assertSame('2025-07-01', $body['nights'][0]['date']);
        self::assertSame(10000, $body['nights'][0]['amountCents']);
        self::assertArrayHasKey('discountPercent', $body['nights'][0]);
        self::assertNull($body['nights'][0]['discountPercent']);
        self::assertSame(10000, $body['nights'][0]['effectiveAmountCents']);
    }

    #[Test]
    public function itReturnsDiscountedNightsWhenPromotionCoversPartOfPeriod(): void
    {
        $client = static::createAuthenticatedClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'PUT',
            uri: "/api/v1/rooms/{$roomId}/base-rate",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amount' => 100.00], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: "/api/v1/rooms/{$roomId}/promotions",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-07-02', 'checkOut' => '2025-07-04', 'discountPercent' => 20], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'GET',
            uri: "/api/v1/rooms/{$roomId}/pricing-quote?checkIn=2025-07-01&checkOut=2025-07-04",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{totalAmountCents: int, nights: array<array{date: string, amountCents: int, discountPercent: int|null, effectiveAmountCents: int}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(3, $body['nights']);

        // Night 1: no discount
        self::assertSame('2025-07-01', $body['nights'][0]['date']);
        self::assertNull($body['nights'][0]['discountPercent']);
        self::assertSame(10000, $body['nights'][0]['effectiveAmountCents']);

        // Night 2: discounted (20%)
        self::assertSame('2025-07-02', $body['nights'][1]['date']);
        self::assertSame(20, $body['nights'][1]['discountPercent']);
        self::assertSame(8000, $body['nights'][1]['effectiveAmountCents']);

        // Night 3: discounted (20%)
        self::assertSame('2025-07-03', $body['nights'][2]['date']);
        self::assertSame(20, $body['nights'][2]['discountPercent']);
        self::assertSame(8000, $body['nights'][2]['effectiveAmountCents']);

        self::assertSame(26000, $body['totalAmountCents']);
    }

    #[Test]
    public function itReturns404WhenRoomDoesNotExist(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request(
            method: 'GET',
            uri: '/api/v1/rooms/00000000-0000-4000-8000-000000000000/pricing-quote?checkIn=2025-07-01&checkOut=2025-07-04',
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
    public function itReturns422WhenRoomExistsButHasNoBaseRate(): void
    {
        $client = static::createAuthenticatedClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'GET',
            uri: "/api/v1/rooms/{$roomId}/pricing-quote?checkIn=2025-07-01&checkOut=2025-07-04",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-has-no-base-rate', $body['type']);
        self::assertSame('Room Has No Base Rate', $body['title']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $body['status']);
    }

    #[Test]
    public function itReturns422WhenCheckInIsNotBeforeCheckOut(): void
    {
        $client = static::createAuthenticatedClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'GET',
            uri: "/api/v1/rooms/{$roomId}/pricing-quote?checkIn=2025-07-04&checkOut=2025-07-01",
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $client->getResponse()->headers->get('Content-Type'));
        /** @var array<mixed> $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('violations', $data);
    }

    #[Test]
    public function itReturns422WhenCheckInOrCheckOutIsMissing(): void
    {
        $client = static::createAuthenticatedClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'GET',
            uri: "/api/v1/rooms/{$roomId}/pricing-quote",
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
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
