<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class RequestCorrelationListenerTest extends WebTestCase
{
    public function test_response_echoes_incoming_x_request_id(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/v1/hotels',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUEST_ID' => 'my-trace-id-abc123',
            ]
        );

        self::assertSame(
            'my-trace-id-abc123',
            $client->getResponse()->headers->get('X-Request-Id')
        );
    }

    public function test_response_generates_x_request_id_when_header_absent(): void
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
