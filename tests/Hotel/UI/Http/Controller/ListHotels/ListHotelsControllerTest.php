<?php

declare(strict_types=1);

namespace App\Tests\Hotel\UI\Http\Controller\ListHotels;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class ListHotelsControllerTest extends WebTestCase
{
    private const array HOTEL_PARIS = [
        'name' => 'Hotel Ibis Paris',
        'streetAddress' => '15 rue de Rivoli',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    private const array HOTEL_LYON = [
        'name' => 'Hotel Lyon Centre',
        'streetAddress' => '3 place Bellecour',
        'postalCode' => '69002',
        'city' => 'Lyon',
        'country' => 'FR',
    ];

    private const array HOTEL_BERLIN = [
        'name' => 'Hotel Berlin Mitte',
        'streetAddress' => '10 Unter den Linden',
        'postalCode' => '10117',
        'city' => 'Berlin',
        'country' => 'DE',
    ];

    #[Test]
    public function itReturns200WithEmptyDataWhenNoHotelsExist(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/hotels');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<mixed>, meta: array{page: int, limit: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $body['data']);
        self::assertSame(1, $body['meta']['page']);
        self::assertSame(20, $body['meta']['limit']);
        self::assertSame(0, $body['meta']['total']);
        self::assertSame(0, $body['meta']['totalPages']);
    }

    #[Test]
    public function itReturnsRegisteredHotelsSortedByName(): void
    {
        $client = static::createClient();

        $this->registerHotel($client, self::HOTEL_PARIS);
        $this->registerHotel($client, self::HOTEL_LYON);

        $client->request('GET', '/api/v1/hotels');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{name: string}>, meta: array{total: int, totalPages: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(2, $body['meta']['total']);
        self::assertSame(1, $body['meta']['totalPages']);
        self::assertSame('Hotel Ibis Paris', $body['data'][0]['name']);
        self::assertSame('Hotel Lyon Centre', $body['data'][1]['name']);
    }

    #[Test]
    public function itReturnsCorrectHotelShape(): void
    {
        $client = static::createClient();
        $this->registerHotel($client, self::HOTEL_PARIS);

        $client->request('GET', '/api/v1/hotels');

        /** @var array{data: list<array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $hotel = $body['data'][0];

        self::assertNotEmpty($hotel['id']);
        self::assertSame('Hotel Ibis Paris', $hotel['name']);
        self::assertSame('15 rue de Rivoli', $hotel['streetAddress']);
        self::assertSame('75001', $hotel['postalCode']);
        self::assertSame('Paris', $hotel['city']);
        self::assertSame('FR', $hotel['country']);
        self::assertGreaterThan(0, $hotel['createdAt']);
    }

    #[Test]
    public function itPaginatesWithDefaultPageSize(): void
    {
        $client = static::createClient();

        for ($i = 1; $i <= 25; ++$i) {
            $this->registerHotel($client, [
                'name' => sprintf('Hotel %02d', $i),
                'streetAddress' => "{$i} rue Test",
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ]);
        }

        $client->request('GET', '/api/v1/hotels');

        /** @var array{data: list<mixed>, meta: array{page: int, limit: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(20, $body['data']);
        self::assertSame(25, $body['meta']['total']);
        self::assertSame(2, $body['meta']['totalPages']);
    }

    #[Test]
    public function itReturnsSecondPage(): void
    {
        $client = static::createClient();

        for ($i = 1; $i <= 5; ++$i) {
            $this->registerHotel($client, [
                'name' => sprintf('Hotel %02d', $i),
                'streetAddress' => "{$i} rue Test",
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ]);
        }

        $client->request('GET', '/api/v1/hotels?page=2&limit=2');

        /** @var array{data: list<array{name: string}>, meta: array{page: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['data']);
        self::assertSame(2, $body['meta']['page']);
        self::assertSame(5, $body['meta']['total']);
        self::assertSame(3, $body['meta']['totalPages']);
        self::assertSame('Hotel 03', $body['data'][0]['name']);
        self::assertSame('Hotel 04', $body['data'][1]['name']);
    }

    #[Test]
    public function itFiltersByCity(): void
    {
        $client = static::createClient();

        $this->registerHotel($client, self::HOTEL_PARIS);
        $this->registerHotel($client, self::HOTEL_LYON);

        $client->request('GET', '/api/v1/hotels?city=Lyon');

        /** @var array{data: list<array{city: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('Lyon', $body['data'][0]['city']);
    }

    #[Test]
    public function itFiltersByCountry(): void
    {
        $client = static::createClient();

        $this->registerHotel($client, self::HOTEL_PARIS);
        $this->registerHotel($client, self::HOTEL_BERLIN);

        $client->request('GET', '/api/v1/hotels?country=DE');

        /** @var array{data: list<array{country: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('DE', $body['data'][0]['country']);
    }

    #[Test]
    public function itFiltersByCityAndCountry(): void
    {
        $client = static::createClient();

        $this->registerHotel($client, self::HOTEL_PARIS);
        $this->registerHotel($client, self::HOTEL_BERLIN);

        $client->request('GET', '/api/v1/hotels?city=Paris&country=FR');

        /** @var array{data: list<array{name: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('Hotel Ibis Paris', $body['data'][0]['name']);
    }

    #[Test]
    public function itReturns422WhenPageIsZero(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/hotels?page=0');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WhenLimitExceedsMaximum(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/hotels?limit=101');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function test_filters_by_amenities(): void
    {
        $client = self::createClient();

        // Register two hotels
        $client->request('POST', '/api/v1/hotels', content: json_encode([
            'name' => 'Pool Gym Hotel',
            'streetAddress' => '10 rue Filter',
            'postalCode' => '75002',
            'city' => 'Lyon',
            'country' => 'FR',
        ], \JSON_THROW_ON_ERROR), server: ['CONTENT_TYPE' => 'application/json']);
        /** @var array{id: string} $dataA */
        $dataA = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $idA = $dataA['id'];

        $client->request('POST', '/api/v1/hotels', content: json_encode([
            'name' => 'Pool Only Hotel',
            'streetAddress' => '11 rue Filter',
            'postalCode' => '75002',
            'city' => 'Lyon',
            'country' => 'FR',
        ], \JSON_THROW_ON_ERROR), server: ['CONTENT_TYPE' => 'application/json']);
        /** @var array{id: string} $dataB */
        $dataB = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $idB = $dataB['id'];

        // Declare amenities
        $client->request('PATCH', "/api/v1/hotels/{$idA}/amenities", content: json_encode(['amenities' => ['pool', 'gym']], \JSON_THROW_ON_ERROR), server: ['CONTENT_TYPE' => 'application/json']);
        $client->request('PATCH', "/api/v1/hotels/{$idB}/amenities", content: json_encode(['amenities' => ['pool']], \JSON_THROW_ON_ERROR), server: ['CONTENT_TYPE' => 'application/json']);

        // Filter pool only — both match
        $client->request('GET', '/api/v1/hotels?amenities[]=pool&city=Lyon');
        self::assertResponseIsSuccessful();
        /** @var array{data: list<array{id: string}>} $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $ids = array_column($data['data'], 'id');
        self::assertContains($idA, $ids);
        self::assertContains($idB, $ids);

        // Filter pool+gym — only idA
        $client->request('GET', '/api/v1/hotels?amenities[]=pool&amenities[]=gym&city=Lyon');
        self::assertResponseIsSuccessful();
        /** @var array{data: list<array{id: string}>} $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $ids = array_column($data['data'], 'id');
        self::assertContains($idA, $ids);
        self::assertNotContains($idB, $ids);

        // Unknown amenity — 422
        $client->request('GET', '/api/v1/hotels?amenities[]=not_an_amenity');
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @param array<string, string> $payload
     */
    private function registerHotel(KernelBrowser $client, array $payload): void
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }
}
