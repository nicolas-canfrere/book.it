<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class NotAcceptableListenerTest extends WebTestCase
{
    /** @return iterable<string, array{string}> */
    public static function acceptableTypes(): iterable
    {
        yield 'application/json' => ['application/json'];
        yield 'wildcard' => ['*/*'];
        yield 'application wildcard' => ['application/*'];
        yield 'json with quality' => ['application/json;q=0.9,text/html;q=0.1'];
        yield 'wildcard with quality' => ['text/html;q=0.9,*/*;q=0.1'];
    }

    #[DataProvider('acceptableTypes')]
    #[Test]
    public function itAllowsRequestWhenAcceptIsCompatible(string $accept): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/hotels', server: ['HTTP_ACCEPT' => $accept]);

        self::assertNotSame(406, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns406WhenAcceptExcludesJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/hotels', server: ['HTTP_ACCEPT' => 'text/html']);

        self::assertResponseStatusCodeSame(406);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function itReturns406ForMultipleNonJsonTypes(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/hotels', server: ['HTTP_ACCEPT' => 'text/html,text/xml']);

        self::assertResponseStatusCodeSame(406);
    }

    #[Test]
    public function itPassesThroughWhenNoAcceptHeader(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/hotels');

        self::assertNotSame(406, $client->getResponse()->getStatusCode());
    }
}
