<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class RequestCorrelationListenerTest extends WebTestCase
{
    public function test_response_echoes_incoming_x_request_id_when_valid_uuid_v4(): void
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

    public function test_response_generates_x_request_id_when_incoming_header_is_not_valid_uuid_v4(): void
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
