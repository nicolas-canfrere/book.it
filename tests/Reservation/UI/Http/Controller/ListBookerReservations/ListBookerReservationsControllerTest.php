<?php

declare(strict_types=1);

namespace App\Tests\Reservation\UI\Http\Controller\ListBookerReservations;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class ListBookerReservationsControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itReturnsPaginatedReservationsForBooker(): void
    {
        $client = static::createAuthenticatedClient();
        [$bookerId, $roomTypeId] = $this->setupBookerAndRoom($client);
        $this->createReservation($client, $bookerId, $roomTypeId, '2030-06-01', '2030-06-03');
        $this->createReservation($client, $bookerId, $roomTypeId, '2030-07-01', '2030-07-03');

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&page=1&limit=10");

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{data: list<array{id: string, status: string}>, meta: array{page: int, limit: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['data']);
        self::assertSame(1, $body['meta']['page']);
        self::assertSame(10, $body['meta']['limit']);
        self::assertSame(2, $body['meta']['total']);
        self::assertSame(1, $body['meta']['totalPages']);
        self::assertSame('pending', $body['data'][0]['status']);
    }

    #[Test]
    public function itReturnsEmptyDataWhenPageExceedsTotal(): void
    {
        $client = static::createAuthenticatedClient();
        [$bookerId, $roomTypeId] = $this->setupBookerAndRoom($client);
        $this->createReservation($client, $bookerId, $roomTypeId, '2030-06-01', '2030-06-03');

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&page=2&limit=10");

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{data: list<mixed>, meta: array{total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(0, $body['data']);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame(1, $body['meta']['totalPages']);
    }

    #[Test]
    public function itReturnsEmptyListForUnknownBooker(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/reservations?bookerId=00000000-0000-4000-8000-000000000099&page=1&limit=20');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{data: list<mixed>, meta: array{total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(0, $body['data']);
        self::assertSame(0, $body['meta']['total']);
        self::assertSame(0, $body['meta']['totalPages']);
    }

    #[Test]
    public function itReturns422WhenBookerIdIsMissing(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/reservations?page=1&limit=20');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $client->getResponse()->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WhenBookerIdIsNotAUuid(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/reservations?bookerId=not-a-uuid');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenLimitExceeds100(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/reservations?bookerId=00000000-0000-4000-8000-000000000001&limit=101');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenPageIsZero(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/reservations?bookerId=00000000-0000-4000-8000-000000000001&page=0');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itFiltersByStatus(): void
    {
        $client = static::createAuthenticatedClient();
        [$bookerId, $roomTypeId] = $this->setupBookerAndRoom($client);
        $this->createReservation($client, $bookerId, $roomTypeId, '2030-06-01', '2030-06-03');

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&status=pending");

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array{data: list<array{status: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&status=cancelled");

        /** @var array{meta: array{total: int}} $emptyBody */
        $emptyBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(0, $emptyBody['meta']['total']);
    }

    #[Test]
    public function itFiltersByUpcomingPeriod(): void
    {
        $client = static::createAuthenticatedClient();
        [$bookerId, $roomTypeId] = $this->setupBookerAndRoom($client);
        $farFuture = (new \DateTimeImmutable('+30 days'))->format('Y-m-d');
        $farFutureCheckOut = (new \DateTimeImmutable('+32 days'))->format('Y-m-d');
        $this->createReservation($client, $bookerId, $roomTypeId, $farFuture, $farFutureCheckOut);

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&period=upcoming");

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array{meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&period=past");

        /** @var array{meta: array{total: int}} $emptyBody */
        $emptyBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(0, $emptyBody['meta']['total']);
    }

    #[Test]
    public function itReturns422WhenStatusIsInvalid(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/reservations?bookerId=00000000-0000-4000-8000-000000000001&status=not-a-status');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenPeriodIsInvalid(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/reservations?bookerId=00000000-0000-4000-8000-000000000001&period=not-a-period');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    /** @return array{string, string, string} [bookerId, roomTypeId, roomId] */
    private function setupBookerAndRoom(KernelBrowser $client): array
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Test Hotel',
            'streetAddress' => '1 rue de la Paix',
            'postalCode' => '75001',
            'city' => 'Paris',
            'country' => 'FR',
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $hotel */
        $hotel = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/hotels/{$hotel['id']}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Standard',
            'livingSpaceCount' => 1,
            'guestCapacity' => 2,
            'isAccessible' => false,
            'bedComposition' => [['type' => 'double', 'count' => 1]],
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $roomType */
        $roomType = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/hotels/{$hotel['id']}/rooms", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'number' => '101',
            'floor' => 1,
            'roomTypeId' => $roomType['id'],
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $room */
        $room = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request('PUT', "/api/v1/rooms/{$room['id']}/base-rate", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'amount' => 100,
        ], \JSON_THROW_ON_ERROR));

        $client->request('POST', '/api/v1/bookers', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'firstName' => 'Alice',
            'lastName' => 'Martin',
            'email' => 'alice.' . uniqid() . '@example.com',
            'phone' => '+33612345678',
            'dateOfBirth' => '1990-01-01',
            'password' => 'SecurePass123!',
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $booker */
        $booker = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return [$booker['id'], $roomType['id'], $room['id']];
    }

    private function createReservation(KernelBrowser $client, string $bookerId, string $roomTypeId, string $checkIn, string $checkOut): void
    {
        $client->request('POST', '/api/v1/reservations', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'roomTypeId' => $roomTypeId,
            'bookerId' => $bookerId,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'guestCount' => 1,
        ], \JSON_THROW_ON_ERROR));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }
}
