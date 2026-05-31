<?php

declare(strict_types=1);

namespace App\Tests\Hotel\UI\Http\Controller\DeclareHotelAmenities;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class DeclareHotelAmenitiesControllerTest extends WebTestCase
{
    public function test_declares_amenities_on_existing_hotel(): void
    {
        $client = self::createClient();
        $id = $this->registerHotel($client);

        $client->request(
            method: 'PATCH',
            uri: "/api/v1/hotels/{$id}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['pool', 'gym']], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(204);
    }

    public function test_replaces_amenities_with_empty_list(): void
    {
        $client = self::createClient();
        $id = $this->registerHotel($client, 'Empty Amenities Hotel');

        $client->request(
            method: 'PATCH',
            uri: "/api/v1/hotels/{$id}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['pool']], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(204);

        $client->request(
            method: 'PATCH',
            uri: "/api/v1/hotels/{$id}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => []], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(204);
    }

    public function test_returns_404_for_unknown_hotel(): void
    {
        $client = self::createClient();

        $client->request(
            method: 'PATCH',
            uri: '/api/v1/hotels/00000000-0000-4000-a000-000000000000/amenities',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['pool']], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function test_returns_422_for_unknown_amenity_value(): void
    {
        $client = self::createClient();
        $id = $this->registerHotel($client, 'Invalid Amenity Hotel');

        $client->request(
            method: 'PATCH',
            uri: "/api/v1/hotels/{$id}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['not_a_real_amenity']], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function test_returns_422_for_duplicate_values(): void
    {
        $client = self::createClient();
        $id = $this->registerHotel($client, 'Duplicate Amenity Hotel');

        $client->request(
            method: 'PATCH',
            uri: "/api/v1/hotels/{$id}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['pool', 'pool']], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    private function registerHotel(KernelBrowser $client, string $name = 'Test Hotel'): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => $name,
                'streetAddress' => '1 rue Test',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ], \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $data['id'];
    }
}
