<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class RequestCorrelationListenerTest extends WebTestCase
{
    #[Test]
    public function itResponseEchoesIncomingXRequestIdWhenValidUuidV4(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/v1/hotels',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUEST_ID' => '550e8400-e29b-4d00-a716-446655440000',
            ]
        );

        self::assertSame(
            '550e8400-e29b-4d00-a716-446655440000',
            $client->getResponse()->headers->get('X-Request-Id')
        );
    }

    #[Test]
    public function itResponseGeneratesXRequestIdWhenIncomingHeaderIsNotValidUuidV4(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/v1/hotels',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUEST_ID' => 'not-a-valid-uuid',
            ]
        );

        $id = $client->getResponse()->headers->get('X-Request-Id');
        self::assertNotNull($id);
        self::assertNotSame('not-a-valid-uuid', $id);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        );
    }

    #[Test]
    public function itResponseGeneratesXRequestIdWhenHeaderAbsent(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/v1/hotels',
            server: ['HTTP_ACCEPT' => 'application/json']
        );

        $id = $client->getResponse()->headers->get('X-Request-Id');
        self::assertNotNull($id);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        );
    }
}
