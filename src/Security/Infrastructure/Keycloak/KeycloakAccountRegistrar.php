<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use Psr\Log\LoggerInterface;

final class KeycloakAccountRegistrar implements AccountRegistrarInterface
{
    public function __construct(
        private readonly KeycloakHttpClientInterface $keycloakClient,
        private readonly IdentityMappingRepository $mappingRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function register(string $internalId, string $context, string $email, string $password): void
    {
        $response = $this->keycloakClient->createUser($email, $password);
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
            $this->keycloakClient->deleteUser($keycloakId);
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

    public function assignRole(string $internalId, string $context, string $roleName): void
    {
        $keycloakId = $this->mappingRepository->findExternalId($internalId, $context);
        if (null === $keycloakId) {
            $this->logger->error('Cannot assign role: no Keycloak mapping found', [
                'internal_id' => $internalId,
                'context' => $context,
                'role' => $roleName,
            ]);
            throw new \RuntimeException("No Keycloak mapping found for {$internalId} (context: {$context})");
        }

        $this->keycloakClient->assignRealmRole($keycloakId, $roleName);

        $this->logger->info('Realm role assigned', [
            'internal_id' => $internalId,
            'context' => $context,
            'keycloak_id' => $keycloakId,
            'role' => $roleName,
        ]);
    }
}
