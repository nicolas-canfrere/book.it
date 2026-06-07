<?php

declare(strict_types=1);

namespace App\Tests\Reservation\UI\Http\Controller\GetReservation;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetReservationControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itReturns200WithAllFields(): void
    {
        $client = static::createAuthenticatedClient();
        $reservationId = $this->createReservation($client);

        $client->request('GET', "/api/v1/reservations/{$reservationId}");

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{id: string, roomId: string, bookerId: string, checkIn: string, checkOut: string, totalPrice: int, guestCount: int, status: string, cancellationTerms: array{daysThreshold: int|null}, priceBreakdown: list<mixed>, createdAt: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($reservationId, $body['id']);
        self::assertSame('pending', $body['status']);
        self::assertSame('2030-06-01', $body['checkIn']);
        self::assertSame('2030-06-03', $body['checkOut']);
        self::assertSame(20000, $body['totalPrice']); // 2 nights × 10000
        self::assertSame(1, $body['guestCount']);
        self::assertNull($body['cancellationTerms']['daysThreshold']);
        self::assertNotEmpty($body['priceBreakdown']);
        self::assertNotEmpty($body['createdAt']);
    }

    #[Test]
    public function itReturns404WhenReservationDoesNotExist(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/reservations/00000000-0000-4000-8000-000000000099');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $client->getResponse()->headers->get('Content-Type'));

        /** @var array{type: string, status: int} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/reservation-not-found', $body['type']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    private function createReservation(KernelBrowser $client): string
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

        $client->request('POST', '/api/v1/reservations', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'roomId' => $room['id'],
            'bookerId' => $booker['id'],
            'checkIn' => '2030-06-01',
            'checkOut' => '2030-06-03',
            'guestCount' => 1,
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $reservation */
        $reservation = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $reservation['id'];
    }
}
