<?php

declare(strict_types=1);

namespace App\Tests\Payment\Functional\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class PaymentWebhookControllerTest extends WebTestCase
{
    public function test_success_webhook_returns_204(): void
    {
        $client = static::createClient();
        $this->post($client, '/api/v1/payment/webhooks/success', ['reservation_id' => '550e8400-e29b-41d4-a716-446655440001']);
        self::assertResponseStatusCodeSame(204);
    }

    public function test_failed_webhook_returns_204(): void
    {
        $client = static::createClient();
        $this->post($client, '/api/v1/payment/webhooks/failed', ['reservation_id' => '550e8400-e29b-41d4-a716-446655440001']);
        self::assertResponseStatusCodeSame(204);
    }

    public function test_cancel_webhook_returns_204(): void
    {
        $client = static::createClient();
        $this->post($client, '/api/v1/payment/webhooks/cancel', ['reservation_id' => '550e8400-e29b-41d4-a716-446655440001']);
        self::assertResponseStatusCodeSame(204);
    }

    public function test_success_webhook_returns_422_if_reservation_id_missing(): void
    {
        $client = static::createClient();
        $this->post($client, '/api/v1/payment/webhooks/success', []);
        self::assertResponseStatusCodeSame(422);
    }

    /** @param array<string, string> $body */
    private function post(KernelBrowser $client, string $url, array $body): void
    {
        $client->request(
            method: 'POST',
            uri: $url,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($body, \JSON_THROW_ON_ERROR),
        );
    }
}
