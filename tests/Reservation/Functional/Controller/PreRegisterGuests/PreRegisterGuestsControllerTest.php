<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Functional\Controller\PreRegisterGuests;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class PreRegisterGuestsControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itPreRegistersGuestsAndReturns204(): void
    {
        $client = static::createAuthenticatedClient();
        $reservationId = $this->createConfirmedReservation($client);

        $client->request(
            method: 'PUT',
            uri: "/api/v1/reservations/{$reservationId}/guests",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                    ['firstName' => 'Bob', 'lastName' => 'Jones', 'dateOfBirth' => '1992-03-20'],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itReturns422WhenGuestFirstNameIsBlank(): void
    {
        $client = static::createAuthenticatedClient();
        $reservationId = $this->createConfirmedReservation($client);

        $client->request(
            method: 'PUT',
            uri: "/api/v1/reservations/{$reservationId}/guests",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => '', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns404WhenReservationNotFound(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request(
            method: 'PUT',
            uri: '/api/v1/reservations/550e8400-e29b-41d4-a716-446655440099/guests',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['guests' => [['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15']]], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function itPreRegistersGuestsOnPendingReservation(): void
    {
        $client = static::createAuthenticatedClient();
        $reservationId = $this->createPendingReservation($client);

        $client->request(
            method: 'PUT',
            uri: "/api/v1/reservations/{$reservationId}/guests",
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
    public function itReturns415WhenContentTypeIsNotJson(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request(
            method: 'PUT',
            uri: '/api/v1/reservations/550e8400-e29b-41d4-a716-446655440099/guests',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: 'guests[]=Alice',
        );

        self::assertResponseStatusCodeSame(415);
    }

    #[Test]
    public function itReturns409WhenCheckInDateIsTodayOrPassed(): void
    {
        $client = static::createAuthenticatedClient();
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
        $reservationId = $this->createPendingReservation($client, $today, $tomorrow);

        $client->request(
            method: 'PUT',
            uri: "/api/v1/reservations/{$reservationId}/guests",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);
    }

    /** Creates a confirmed reservation with a far-future check-in (pre-registration window). */
    private function createConfirmedReservation(KernelBrowser $client): string
    {
        $reservationId = $this->createPendingReservation($client, '2099-07-01', '2099-07-03');
        $this->confirmReservation($client, $reservationId);

        return $reservationId;
    }

    private function createPendingReservation(
        KernelBrowser $client,
        string $checkIn = '2099-07-01',
        string $checkOut = '2099-07-03',
    ): string {
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
                'guestCount' => 2,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function confirmReservation(KernelBrowser $client, string $reservationId): void
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/payment/webhooks/success',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reservation_id' => $reservationId], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
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
                'guestCapacity' => 3,
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
