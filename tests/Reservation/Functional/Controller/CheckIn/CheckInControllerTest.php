<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Functional\Controller\CheckIn;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class CheckInControllerTest extends WebTestCase
{
    #[Test]
    public function itCheckInReturns204(): void
    {
        $client = static::createClient();
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
        $reservationId = $this->createConfirmedReservation($client, $today, $tomorrow);

        $client->request(
            method: 'POST',
            uri: "/api/v1/reservations/{$reservationId}/check-in",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itReturns422WhenGuestLastNameIsBlank(): void
    {
        $client = static::createClient();
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
        $reservationId = $this->createConfirmedReservation($client, $today, $tomorrow);

        $client->request(
            method: 'POST',
            uri: "/api/v1/reservations/{$reservationId}/check-in",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => 'Alice', 'lastName' => '', 'dateOfBirth' => '1990-01-15'],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns404WhenReservationNotFound(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations/550e8400-e29b-41d4-a716-446655440099/check-in',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['guests' => [['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15']]], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function itReturns409WhenReservationIsCancelled(): void
    {
        $client = static::createClient();
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
        $reservationId = $this->createCancelledReservation($client, $today, $tomorrow);

        $client->request(
            method: 'POST',
            uri: "/api/v1/reservations/{$reservationId}/check-in",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function itReturns409WhenCheckInIsInTheFuture(): void
    {
        $client = static::createClient();
        $reservationId = $this->createConfirmedReservation($client, '2099-07-01', '2099-07-03');

        $client->request(
            method: 'POST',
            uri: "/api/v1/reservations/{$reservationId}/check-in",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function itReturns415WhenContentTypeIsNotJson(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations/550e8400-e29b-41d4-a716-446655440000/check-in',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: 'guests[]=Alice',
        );

        self::assertResponseStatusCodeSame(415);
    }

    private function createCancelledReservation(KernelBrowser $client, string $checkIn, string $checkOut): string
    {
        [$roomId, $bookerId] = $this->setupRoomAndBooker($client);
        $this->setBaseRate($client, $roomId, 10000);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => $checkIn,
                'checkOut' => $checkOut,
                'guestCount' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $reservationId = $body['id'];

        $client->request(
            method: 'POST',
            uri: '/api/v1/payment/webhooks/cancel',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reservation_id' => $reservationId], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        return $reservationId;
    }

    private function createConfirmedReservation(KernelBrowser $client, string $checkIn, string $checkOut): string
    {
        [$roomId, $bookerId] = $this->setupRoomAndBooker($client);
        $this->setBaseRate($client, $roomId, 10000);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => $checkIn,
                'checkOut' => $checkOut,
                'guestCount' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $reservationId = $body['id'];

        $client->request(
            method: 'POST',
            uri: '/api/v1/payment/webhooks/success',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reservation_id' => $reservationId], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        return $reservationId;
    }

    /** @return array{string, string} [roomId, bookerId] */
    private function setupRoomAndBooker(KernelBrowser $client): array
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
                'guestCapacity' => 2,
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
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $bookerBody */
        $bookerBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return [$roomBody['id'], $bookerBody['id']];
    }

    private function setBaseRate(KernelBrowser $client, string $roomId, int $amountCents): void
    {
        $client->request(
            method: 'PUT',
            uri: "/api/v1/rooms/{$roomId}/base-rate",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amount' => $amountCents / 100], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
