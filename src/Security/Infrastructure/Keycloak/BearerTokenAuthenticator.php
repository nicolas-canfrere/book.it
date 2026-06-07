<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class BearerTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly KeycloakJwksProviderInterface $jwksProvider,
        private readonly string $keycloakIssuer,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        throw new \RuntimeException('Not implemented');
    }

    public function authenticate(Request $request): Passport
    {
        throw new \RuntimeException('Not implemented');
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
