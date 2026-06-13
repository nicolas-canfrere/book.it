<?php

declare(strict_types=1);

namespace App\Tests\Payment\Infrastructure\Security;

use App\Payment\Infrastructure\Security\HmacWebhookAuthenticator;
use App\Payment\Infrastructure\Security\WebhookUser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

#[Group('unit')]
final class HmacWebhookAuthenticatorTest extends TestCase
{
    private const string SECRET = 'test-secret';
    private HmacWebhookAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new HmacWebhookAuthenticator(self::SECRET);
    }

    #[Test]
    public function itSupportsRequestWithSignatureHeader(): void
    {
        $request = Request::create('/payment/webhooks/success', 'POST');
        $request->headers->set('X-Webhook-Signature', 't=1234,v1=abc');

        self::assertTrue($this->authenticator->supports($request));
    }

    #[Test]
    public function itDoesNotSupportRequestWithoutSignatureHeader(): void
    {
        $request = Request::create('/payment/webhooks/success', 'POST');

        self::assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function itAuthenticatesValidSignature(): void
    {
        $body = '{"reservation_id":"abc","event_id":"def"}';
        $timestamp = time();
        $payload = $timestamp . "\n" . $body;
        $hmac = hash_hmac('sha256', $payload, self::SECRET);
        $header = "t={$timestamp},v1={$hmac}";

        $request = Request::create('/payment/webhooks/success', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Webhook-Signature', $header);

        $passport = $this->authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertInstanceOf(WebhookUser::class, $passport->getUser());
    }

    #[Test]
    public function itRejectsInvalidSignature(): void
    {
        $this->expectException(AuthenticationException::class);

        $body = '{"reservation_id":"abc"}';
        $timestamp = time();
        $header = "t={$timestamp},v1=invalidsignature";

        $request = Request::create('/payment/webhooks/success', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Webhook-Signature', $header);

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function itRejectsMalformedHeader(): void
    {
        $this->expectException(AuthenticationException::class);

        $request = Request::create('/payment/webhooks/success', 'POST');
        $request->headers->set('X-Webhook-Signature', 'not-valid-format');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function itRejectsExpiredTimestamp(): void
    {
        $this->expectException(AuthenticationException::class);

        $body = '{"reservation_id":"abc"}';
        $timestamp = time() - 400;
        $payload = $timestamp . "\n" . $body;
        $hmac = hash_hmac('sha256', $payload, self::SECRET);
        $header = "t={$timestamp},v1={$hmac}";

        $request = Request::create('/payment/webhooks/success', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Webhook-Signature', $header);

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function itReturns401OnAuthenticationFailure(): void
    {
        $request = Request::create('/payment/webhooks/success', 'POST');
        $response = $this->authenticator->onAuthenticationFailure($request, new AuthenticationException('Invalid'));

        self::assertSame(401, $response->getStatusCode());
    }
}
