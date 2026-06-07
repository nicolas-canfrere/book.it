<?php

declare(strict_types=1);

namespace App\Tests\Search\Functional;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class SearchAvailableRoomTypesTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itReturns200WithEmptyResultsWhenNothingMatches(): void
    {
        $client = static::createAuthenticatedClient();
        $client->request('GET', '/api/v1/search?city=Nowhere&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(200);
        self::assertJson((string) $client->getResponse()->getContent());

        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertSame([], $body);
    }

    #[Test]
    public function itReturns422WhenCityIsMissing(): void
    {
        $client = static::createAuthenticatedClient();
        $client->request('GET', '/api/v1/search?checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns422WhenGuestsIsZero(): void
    {
        $client = static::createAuthenticatedClient();
        $client->request('GET', '/api/v1/search?city=Paris&checkIn=2026-07-01&checkOut=2026-07-05&guests=0');

        self::assertResponseStatusCodeSame(422);
    }
}
