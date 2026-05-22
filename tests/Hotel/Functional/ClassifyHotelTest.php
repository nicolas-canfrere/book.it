<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Functional;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class ClassifyHotelTest extends WebTestCase
{
    public function test_it_sets_a_star_rating_on_a_hotel(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 4,
            'superior' => false,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(204);

        $client->request('GET', "/api/v1/hotels/{$id}");
        /** @var array{starRating: array{stars: int, superior: bool}|null} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(['stars' => 4, 'superior' => false], $body['starRating']);
    }

    public function test_it_sets_a_superior_star_rating(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 5,
            'superior' => true,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(204);

        $client->request('GET', "/api/v1/hotels/{$id}");
        /** @var array{starRating: array{stars: int, superior: bool}|null} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(['stars' => 5, 'superior' => true], $body['starRating']);
    }

    public function test_it_removes_a_star_rating_when_stars_is_null(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 3,
            'superior' => false,
        ], \JSON_THROW_ON_ERROR));
        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => null,
            'superior' => false,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(204);

        $client->request('GET', "/api/v1/hotels/{$id}");
        /** @var array{starRating: array{stars: int, superior: bool}|null} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertNull($body['starRating']);
    }

    public function test_it_returns_404_for_unknown_hotel(): void
    {
        $client = static::createClient();

        $client->request('PATCH', '/api/v1/hotels/00000000-0000-4000-a000-000000000000/star-rating', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 3,
            'superior' => false,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(404);
    }

    public function test_it_returns_422_when_superior_true_without_stars(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => null,
            'superior' => true,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function test_it_returns_422_when_stars_out_of_range(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 6,
            'superior' => false,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    private function registerHotel(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Hotel Test',
            'streetAddress' => '1 rue Test',
            'postalCode' => '75001',
            'city' => 'Paris',
            'country' => 'FR',
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        return $body['id'];
    }
}
