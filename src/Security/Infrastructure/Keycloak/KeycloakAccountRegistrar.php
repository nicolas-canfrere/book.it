<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KeycloakAccountRegistrar implements AccountRegistrarInterface
{
    private ?string $adminToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly IdentityMappingRepository $mappingRepository,
        private readonly string $keycloakBaseUrl,
        private readonly string $keycloakRealm,
        private readonly string $keycloakClientId,
        private readonly string $keycloakClientSecret,
    ) {
    }

    public function register(string $internalId, string $context, string $email, string $password): void
    {
        $response = $this->httpClient->request(
            'POST',
            "{$this->keycloakBaseUrl}/admin/realms/{$this->keycloakRealm}/users",
            [
                'auth_bearer' => $this->fetchAdminToken(),
                'json' => [
                    'email' => $email,
                    'username' => $email,
                    'emailVerified' => true,
                    'enabled' => true,
                    'credentials' => [[
                        'type' => 'password',
                        'value' => $password,
                        'temporary' => false,
                    ]],
                ],
            ],
        );

        if (201 !== $response->getStatusCode()) {
            throw new AccountRegistrationFailedException($email);
        }

        $location = $response->getHeaders(false)['location'][0] ?? '';
        $keycloakId = basename($location);

        $this->mappingRepository->save($internalId, $context, $keycloakId);
    }

    public function unregister(string $internalId, string $context): void
    {
        $keycloakId = $this->mappingRepository->findExternalId($internalId, $context);
        if (null === $keycloakId) {
            return;
        }

        try {
            $this->httpClient->request(
                'DELETE',
                "{$this->keycloakBaseUrl}/admin/realms/{$this->keycloakRealm}/users/{$keycloakId}",
                ['auth_bearer' => $this->fetchAdminToken()],
            );
        } catch (\Throwable) {
            // best-effort: log and continue
        }

        $this->mappingRepository->delete($internalId, $context);
    }

    private function fetchAdminToken(): string
    {
        if (null !== $this->adminToken) {
            return $this->adminToken;
        }

        $response = $this->httpClient->request(
            'POST',
            "{$this->keycloakBaseUrl}/realms/{$this->keycloakRealm}/protocol/openid-connect/token",
            [
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->keycloakClientId,
                    'client_secret' => $this->keycloakClientSecret,
                ],
            ],
        );

        $token = $response->toArray()['access_token'];
        $this->adminToken = \is_string($token) ? $token : '';

        return $this->adminToken;
    }
}
