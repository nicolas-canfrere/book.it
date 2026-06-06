<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
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

        $statusCode = $response->getStatusCode();

        if (201 !== $statusCode) {
            $this->logger->error('Keycloak account creation failed', [
                'internal_id' => $internalId,
                'context' => $context,
                'email' => $email,
                'status_code' => $statusCode,
            ]);
            throw new AccountRegistrationFailedException($email);
        }

        $location = $response->getHeaders(false)['location'][0] ?? '';
        $keycloakId = basename($location);

        $this->mappingRepository->save($internalId, $context, $keycloakId);

        $this->logger->info('Keycloak account created', [
            'internal_id' => $internalId,
            'context' => $context,
            'keycloak_id' => $keycloakId,
        ]);
    }

    public function unregister(string $internalId, string $context): void
    {
        $keycloakId = $this->mappingRepository->findExternalId($internalId, $context);
        if (null === $keycloakId) {
            $this->logger->debug('Keycloak unregister skipped: no mapping found', [
                'internal_id' => $internalId,
                'context' => $context,
            ]);

            return;
        }

        try {
            $this->httpClient->request(
                'DELETE',
                "{$this->keycloakBaseUrl}/admin/realms/{$this->keycloakRealm}/users/{$keycloakId}",
                ['auth_bearer' => $this->fetchAdminToken()],
            );

            $this->logger->info('Keycloak account deleted', [
                'internal_id' => $internalId,
                'context' => $context,
                'keycloak_id' => $keycloakId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Keycloak account deletion failed (best-effort)', [
                'internal_id' => $internalId,
                'context' => $context,
                'keycloak_id' => $keycloakId,
                'error' => $e->getMessage(),
            ]);
        }

        $this->mappingRepository->delete($internalId, $context);
    }

    private function fetchAdminToken(): string
    {
        if (null !== $this->adminToken) {
            return $this->adminToken;
        }

        $this->logger->debug('Fetching Keycloak admin token', ['realm' => $this->keycloakRealm]);

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
