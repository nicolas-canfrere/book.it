<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class ClassifyHotelTest extends WebTestCase
{
    #[Test]
    public function itItSetsAStarRatingOnAHotel(): void
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

    #[Test]
    public function itItSetsASuperiorStarRating(): void
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

    #[Test]
    public function itItRemovesAStarRatingWhenStarsIsNull(): void
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

    #[Test]
    public function itItReturns404ForUnknownHotel(): void
    {
        $client = static::createClient();

        $client->request('PATCH', '/api/v1/hotels/00000000-0000-4000-a000-000000000000/star-rating', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 3,
            'superior' => false,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function itItReturns422WhenSuperiorTrueWithoutStars(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => null,
            'superior' => true,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itItReturns422WhenStarsOutOfRange(): void
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
