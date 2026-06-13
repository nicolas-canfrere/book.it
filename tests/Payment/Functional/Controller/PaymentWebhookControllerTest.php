<?php

declare(strict_types=1);

namespace App\Tests\Payment\Functional\Controller;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class PaymentWebhookControllerTest extends WebTestCase
{
    private const string RESERVATION_ID = '550e8400-e29b-41d4-a716-446655440001';
    private const string EVENT_ID = '550e8400-e29b-41d4-a716-446655440002';

    #[Test]
    public function itSuccessWebhookReturns204(): void
    {
        $client = static::createClient();
        $this->postSigned($client, '/api/v1/payment/webhooks/success', [
            'reservation_id' => self::RESERVATION_ID,
            'event_id' => self::EVENT_ID,
        ]);
        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itFailedWebhookReturns204(): void
    {
        $client = static::createClient();
        $this->postSigned($client, '/api/v1/payment/webhooks/failed', [
            'reservation_id' => self::RESERVATION_ID,
            'event_id' => '550e8400-e29b-41d4-a716-446655440003',
        ]);
        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itCancelWebhookReturns204(): void
    {
        $client = static::createClient();
        $this->postSigned($client, '/api/v1/payment/webhooks/cancel', [
            'reservation_id' => self::RESERVATION_ID,
            'event_id' => '550e8400-e29b-41d4-a716-446655440004',
        ]);
        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itSuccessWebhookReturns422IfEventIdMissing(): void
    {
        $client = static::createClient();
        $this->postSigned($client, '/api/v1/payment/webhooks/success', [
            'reservation_id' => self::RESERVATION_ID,
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itSuccessWebhookReturns422IfReservationIdMissing(): void
    {
        $client = static::createClient();
        $this->postSigned($client, '/api/v1/payment/webhooks/success', [
            'event_id' => self::EVENT_ID,
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns401WithoutSignatureHeader(): void
    {
        $client = static::createClient();
        $body = json_encode(['reservation_id' => self::RESERVATION_ID, 'event_id' => self::EVENT_ID], \JSON_THROW_ON_ERROR);
        $client->request(
            method: 'POST',
            uri: '/api/v1/payment/webhooks/success',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $body,
        );
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function itReturns401WithInvalidSignature(): void
    {
        $client = static::createClient();
        $body = json_encode(['reservation_id' => self::RESERVATION_ID, 'event_id' => self::EVENT_ID], \JSON_THROW_ON_ERROR);
        $timestamp = time();
        $client->request(
            method: 'POST',
            uri: '/api/v1/payment/webhooks/success',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => "t={$timestamp},v1=invalidsignature",
            ],
            content: $body,
        );
        self::assertResponseStatusCodeSame(401);
    }

    /** @param array<string, string> $body */
    private function postSigned(KernelBrowser $client, string $url, array $body): void
    {
        $content = json_encode($body, \JSON_THROW_ON_ERROR);
        $secret = \is_string($_ENV['PAYMENT_WEBHOOK_SECRET'] ?? null) ? $_ENV['PAYMENT_WEBHOOK_SECRET'] : 'test-webhook-secret';
        $timestamp = time();
        $signedPayload = $timestamp . "\n" . $content;
        $hmac = hash_hmac('sha256', $signedPayload, $secret);

        $client->request(
            method: 'POST',
            uri: $url,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => "t={$timestamp},v1={$hmac}",
            ],
            content: $content,
        );
    }
}
