<?php

declare(strict_types=1);

namespace App\Tests\Reservation\UI\Http\Controller\CreateReservation;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class CreateReservationControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itCreatesAReservationAndReturns201(): void
    {
        $client = static::createAuthenticatedClient();
        [$roomTypeId, $bookerId, $roomId] = $this->setupRoomAndBooker($client, guestCapacity: 3);
        $this->setBaseRate($client, $roomId, 10000);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomTypeId' => $roomTypeId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
                'guestCount' => 2,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, roomId: string, bookerId: string, checkIn: string, checkOut: string, totalPrice: int, guestCount: int, status: string, createdAt: string, cancellationTerms: array{daysThreshold: int|null}, priceBreakdown: list<mixed>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame($roomId, $body['roomId']);
        self::assertSame($bookerId, $body['bookerId']);
        self::assertSame('2030-06-01', $body['checkIn']);
        self::assertSame('2030-06-05', $body['checkOut']);
        self::assertSame(40000, $body['totalPrice']); // 4 nights × 10000
        self::assertSame(2, $body['guestCount']);
        self::assertSame('pending', $body['status']);
        self::assertNotEmpty($body['createdAt']);
        self::assertNull($body['cancellationTerms']['daysThreshold']);
        self::assertNotEmpty($body['priceBreakdown']);
        $firstNight = $body['priceBreakdown'][0];
        self::assertIsArray($firstNight);
        self::assertArrayHasKey('date', $firstNight);
        self::assertArrayHasKey('rateAmountCents', $firstNight);
        self::assertArrayHasKey('discountPercent', $firstNight);
        self::assertArrayHasKey('effectiveAmountCents', $firstNight);
    }

    #[Test]
    public function itReturns422WhenGuestCountExceedsRoomCapacity(): void
    {
        $client = static::createAuthenticatedClient();
        [$roomTypeId, $bookerId, $roomId] = $this->setupRoomAndBooker($client, guestCapacity: 1);
        $this->setBaseRate($client, $roomId, 10000);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomTypeId' => $roomTypeId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
                'guestCount' => 2,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/guest-capacity-exceeded', $body['type']);
    }

    #[Test]
    public function itReturns409WhenRoomTypeDoesNotExist(): void
    {
        $client = static::createAuthenticatedClient();
        [, $bookerId] = $this->setupRoomAndBooker($client);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomTypeId' => '00000000-0000-4000-8000-000000000001',
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
                'guestCount' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-not-available', $body['type']);
    }

    #[Test]
    public function itReturns404WhenBookerDoesNotExist(): void
    {
        $client = static::createAuthenticatedClient();
        [$roomTypeId] = $this->setupRoomAndBooker($client);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomTypeId' => $roomTypeId,
                'bookerId' => '00000000-0000-4000-8000-000000000002',
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
                'guestCount' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/booker-not-found', $body['type']);
    }

    #[Test]
    public function itReturns409WhenRoomIsNotAvailable(): void
    {
        $client = static::createAuthenticatedClient();
        [$roomTypeId, $bookerId, $roomId] = $this->setupRoomAndBooker($client);
        $this->setBaseRate($client, $roomId, 10000);
        $this->blockPeriod($client, $roomId, '2030-06-01', '2030-06-10');

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomTypeId' => $roomTypeId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-03',
                'checkOut' => '2030-06-07',
                'guestCount' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());

        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-not-available', $body['type']);
    }

    #[Test]
    public function itReturns422WhenRoomHasNoPricing(): void
    {
        $client = static::createAuthenticatedClient();
        [$roomTypeId, $bookerId] = $this->setupRoomAndBooker($client);
        // Intentionally NOT setting a base rate

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomTypeId' => $roomTypeId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
                'guestCount' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-not-bookable', $body['type']);
    }

    #[Test]
    public function itReturns422WhenRequestBodyIsInvalid(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['roomTypeId' => 'not-a-uuid', 'checkIn' => '2030-06-01'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    /** @return array{string, string, string} [roomTypeId, bookerId, roomId] */
    private function setupRoomAndBooker(KernelBrowser $client, int $guestCapacity = 2): array
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Test Hotel',
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
                'name' => 'Standard',
                'livingSpaceCount' => 1,
                'guestCapacity' => $guestCapacity,
                'isAccessible' => false,
                'bedComposition' => [['type' => 'double', 'count' => 1]],
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

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'firstName' => 'Alice',
                'lastName' => 'Martin',
                'email' => 'alice.' . uniqid() . '@example.com',
                'phone' => '+33612345678',
                'dateOfBirth' => '1990-01-01',
                'password' => 'SecurePass123!',
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $bookerBody */
        $bookerBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return [$roomTypeBody['id'], $bookerBody['id'], $roomBody['id']];
    }

    private function setBaseRate(KernelBrowser $client, string $roomId, int $amountCents): void
    {
        $client->request(
            method: 'PUT',
            uri: "/api/v1/rooms/{$roomId}/base-rate",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amount' => $amountCents / 100], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    private function blockPeriod(KernelBrowser $client, string $roomId, string $checkIn, string $checkOut): void
    {
        $client->request(
            method: 'POST',
            uri: "/api/v1/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => $checkIn, 'checkOut' => $checkOut], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }
}
