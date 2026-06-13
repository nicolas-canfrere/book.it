<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Security;

use App\Shared\Infrastructure\Http\ProblemDetail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class HmacWebhookAuthenticator extends AbstractAuthenticator
{
    private const int TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function __construct(private string $secret)
    {
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('X-Webhook-Signature');
    }

    public function authenticate(Request $request): Passport
    {
        $header = $request->headers->get('X-Webhook-Signature', '');

        if (!preg_match('/^t=(\d+),v1=([a-f0-9]+)$/', $header, $matches)) {
            throw new AuthenticationException('Invalid X-Webhook-Signature header format');
        }

        $timestamp = (int) $matches[1];
        $receivedHmac = $matches[2];

        if (abs(time() - $timestamp) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            throw new AuthenticationException('Webhook timestamp is too old or in the future');
        }

        $signedPayload = $timestamp . "\n" . $request->getContent();
        $expectedHmac = hash_hmac('sha256', $signedPayload, $this->secret);

        if (!hash_equals($expectedHmac, $receivedHmac)) {
            throw new AuthenticationException('Invalid webhook signature');
        }

        return new SelfValidatingPassport(
            new UserBadge('payment-provider', static fn() => new WebhookUser())
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $problem = new ProblemDetail(
            type: 'about:blank',
            title: 'Unauthorized',
            status: Response::HTTP_UNAUTHORIZED,
            detail: 'Invalid or missing webhook signature.',
        );

        return new JsonResponse(
            $problem->toArray(),
            $problem->status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
