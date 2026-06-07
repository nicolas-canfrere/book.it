<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Functional;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class ListHotelsTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itFiltersHotelsWithMinStars(): void
    {
        $client = static::createAuthenticatedClient();

        $id4Stars = $this->registerHotel($client, 'Hotel Four Stars');
        $this->classifyHotel($client, $id4Stars, 4, false);

        $id2Stars = $this->registerHotel($client, 'Hotel Two Stars');
        $this->classifyHotel($client, $id2Stars, 2, false);

        $client->request('GET', '/api/v1/hotels?minStars=3');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{name: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('Hotel Four Stars', $body['data'][0]['name']);
    }

    #[Test]
    public function itExcludesUnratedHotelsWhenMinStarsIsSet(): void
    {
        $client = static::createAuthenticatedClient();

        $idRated = $this->registerHotel($client, 'Hotel Rated');
        $this->classifyHotel($client, $idRated, 3, false);

        $this->registerHotel($client, 'Hotel Unrated');

        $client->request('GET', '/api/v1/hotels?minStars=1');

        /** @var array{data: list<array{name: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('Hotel Rated', $body['data'][0]['name']);
    }

    #[Test]
    public function itReturnsAllHotelsWhenNoMinStarsFilter(): void
    {
        $client = static::createAuthenticatedClient();

        $idRated = $this->registerHotel($client, 'Hotel With Stars');
        $this->classifyHotel($client, $idRated, 3, false);

        $this->registerHotel($client, 'Hotel Without Stars');

        $client->request('GET', '/api/v1/hotels');

        /** @var array{data: list<mixed>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(2, $body['meta']['total']);
    }

    #[Test]
    public function itIncludesStarRatingFieldInCatalogueResponse(): void
    {
        $client = static::createAuthenticatedClient();

        $idRated = $this->registerHotel($client, 'Hotel Rated Stars');
        $this->classifyHotel($client, $idRated, 4, true);

        $this->registerHotel($client, 'Hotel Unrated Stars');

        $client->request('GET', '/api/v1/hotels');

        /** @var array{data: list<array{name: string, starRating: array{stars: int, superior: bool}|null}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(2, count($body['data']));

        $ratedHotel = null;
        $unratedHotel = null;
        foreach ($body['data'] as $hotel) {
            if ('Hotel Rated Stars' === $hotel['name']) {
                $ratedHotel = $hotel;
            } elseif ('Hotel Unrated Stars' === $hotel['name']) {
                $unratedHotel = $hotel;
            }
        }

        self::assertNotNull($ratedHotel);
        self::assertNotNull($unratedHotel);
        self::assertSame(['stars' => 4, 'superior' => true], $ratedHotel['starRating']);
        self::assertNull($unratedHotel['starRating']);
    }

    private function registerHotel(KernelBrowser $client, string $name): string
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

        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function classifyHotel(KernelBrowser $client, string $id, int $stars, bool $superior): void
    {
        $client->request(
            method: 'PATCH',
            uri: "/api/v1/hotels/{$id}/star-rating",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'stars' => $stars,
                'superior' => $superior,
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }
}
