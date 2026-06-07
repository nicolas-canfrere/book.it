<?php

declare(strict_types=1);

namespace App\Tests\Payment\Functional\Controller;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[Group('functional')]
final class PaymentWebhookControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itSuccessWebhookReturns204(): void
    {
        $client = static::createAuthenticatedClient();
        $this->post($client, '/api/v1/payment/webhooks/success', ['reservation_id' => '550e8400-e29b-41d4-a716-446655440001']);
        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itFailedWebhookReturns204(): void
    {
        $client = static::createAuthenticatedClient();
        $this->post($client, '/api/v1/payment/webhooks/failed', ['reservation_id' => '550e8400-e29b-41d4-a716-446655440001']);
        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itCancelWebhookReturns204(): void
    {
        $client = static::createAuthenticatedClient();
        $this->post($client, '/api/v1/payment/webhooks/cancel', ['reservation_id' => '550e8400-e29b-41d4-a716-446655440001']);
        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itSuccessWebhookReturns422IfReservationIdMissing(): void
    {
        $client = static::createAuthenticatedClient();
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
