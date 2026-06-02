<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
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
    public function test_allows_request_when_accept_is_compatible(string $accept): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/hotels', server: ['HTTP_ACCEPT' => $accept]);

        self::assertNotSame(406, $client->getResponse()->getStatusCode());
    }

    public function test_returns_406_when_accept_excludes_json(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/hotels', server: ['HTTP_ACCEPT' => 'text/html']);

        self::assertResponseStatusCodeSame(406);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function test_returns_406_for_multiple_non_json_types(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/hotels', server: ['HTTP_ACCEPT' => 'text/html,text/xml']);

        self::assertResponseStatusCodeSame(406);
    }

    public function test_passes_through_when_no_accept_header(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/hotels');

        self::assertNotSame(406, $client->getResponse()->getStatusCode());
    }
}
