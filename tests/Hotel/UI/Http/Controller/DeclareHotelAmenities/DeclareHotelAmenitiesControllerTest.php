<?php

declare(strict_types=1);

namespace App\Tests\Hotel\UI\Http\Controller\DeclareHotelAmenities;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[Group('functional')]
final class DeclareHotelAmenitiesControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itDeclaresAmenitiesOnExistingHotel(): void
    {
        $client = self::createAuthenticatedClient();
        $id = $this->registerHotel($client);

        $client->request(
            method: 'PATCH',
            uri: "/api/v1/hotels/{$id}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['pool', 'gym']], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itReplacesAmenitiesWithEmptyList(): void
    {
        $client = self::createAuthenticatedClient();
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

    #[Test]
    public function itReturns404ForUnknownHotel(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request(
            method: 'PATCH',
            uri: '/api/v1/hotels/00000000-0000-4000-a000-000000000000/amenities',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['pool']], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function itReturns422ForUnknownAmenityValue(): void
    {
        $client = self::createAuthenticatedClient();
        $id = $this->registerHotel($client, 'Invalid Amenity Hotel');

        $client->request(
            method: 'PATCH',
            uri: "/api/v1/hotels/{$id}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['not_a_real_amenity']], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns422ForDuplicateValues(): void
    {
        $client = self::createAuthenticatedClient();
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
