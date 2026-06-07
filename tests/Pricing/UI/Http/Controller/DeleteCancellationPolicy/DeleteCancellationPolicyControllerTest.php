<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\DeleteCancellationPolicy;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class DeleteCancellationPolicyControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itDeletesCancellationPolicyAndReturns204(): void
    {
        $client = static::createAuthenticatedClient();
        $roomId = $this->registerRoomAndGetId($client);

        $this->setCancellationPolicy($client, $roomId, 14);

        $client->request(
            method: 'DELETE',
            uri: "/api/v1/rooms/{$roomId}/cancellation-policy",
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404WhenCancellationPolicyNotFound(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request(
            method: 'DELETE',
            uri: '/api/v1/rooms/00000000-0000-4000-8000-000000000001/cancellation-policy',
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/cancellation-policy-not-found', $body['type']);
        self::assertSame('Cancellation Policy Not Found', $body['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    private function setCancellationPolicy(KernelBrowser $client, string $roomId, int $daysThreshold): void
    {
        $client->request(
            method: 'PUT',
            uri: "/api/v1/rooms/{$roomId}/cancellation-policy",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['daysThreshold' => $daysThreshold], \JSON_THROW_ON_ERROR),
        );
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
