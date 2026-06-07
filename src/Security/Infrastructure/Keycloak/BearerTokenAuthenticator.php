<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class BearerTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly KeycloakJwksProviderInterface $jwksProvider,
        private readonly string $keycloakIssuer,
    ) {
    }

    public function supports(Request $request): bool
    {
        $header = $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr((string) $request->headers->get('Authorization'), 7);

        try {
            $keys = $this->jwksProvider->getPublicKeys();
            if (1 === count($keys)) {
                $payload = JWT::decode($token, reset($keys));
            } else {
                $payload = JWT::decode($token, $keys);
            }
        } catch (\Throwable $e) {
            throw new AuthenticationException('Invalid token: ' . $e->getMessage(), 0, $e);
        }

        if (($payload->iss ?? '') !== $this->keycloakIssuer) {
            throw new AuthenticationException('Invalid token issuer');
        }

        $rawSub = $payload->sub ?? '';
        $sub = \is_scalar($rawSub) ? (string) $rawSub : '';

        return new SelfValidatingPassport(
            new UserBadge($sub, static fn(string $id) => new InMemoryUser($id, null, ['ROLE_OPERATOR']))
        );
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
