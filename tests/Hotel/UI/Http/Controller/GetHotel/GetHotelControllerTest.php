<?php

declare(strict_types=1);

namespace App\Tests\Hotel\UI\Http\Controller\GetHotel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetHotelControllerTest extends WebTestCase
{
    private const array VALID_PAYLOAD = [
        'name' => 'Hotel Ibis Paris',
        'streetAddress' => '15 rue de Rivoli',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    #[Test]
    public function itReturns200WithCorrectHotelShape(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $registered */
        $registered = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $id = $registered['id'];

        $client->request('GET', "/api/v1/hotels/{$id}");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($id, $body['id']);
        self::assertSame('Hotel Ibis Paris', $body['name']);
        self::assertSame('15 rue de Rivoli', $body['streetAddress']);
        self::assertSame('75001', $body['postalCode']);
        self::assertSame('Paris', $body['city']);
        self::assertSame('FR', $body['country']);
        self::assertGreaterThan(0, $body['createdAt']);
    }

    #[Test]
    public function itReturns404WhenHotelDoesNotExist(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/hotels/00000000-0000-0000-0000-000000000000');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns404WhenIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/hotels/not-a-uuid');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }
}
