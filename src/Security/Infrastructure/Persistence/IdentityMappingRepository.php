<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;

class IdentityMappingRepository
{
    public function __construct(
        private readonly Connection $securityConnection,
    ) {
    }

    public function save(string $internalId, string $context, string $externalId): void
    {
        $this->securityConnection->insert('security.identity_mapping', [
            'internal_id' => $internalId,
            'context' => $context,
            'external_id' => $externalId,
        ]);
    }

    public function delete(string $internalId, string $context): void
    {
        $this->securityConnection->delete('security.identity_mapping', [
            'internal_id' => $internalId,
            'context' => $context,
        ]);
    }

    public function findExternalId(string $internalId, string $context): ?string
    {
        $result = $this->securityConnection->fetchOne(
            'SELECT external_id FROM security.identity_mapping WHERE internal_id = ? AND context = ?',
            [$internalId, $context],
        );

        return \is_string($result) ? $result : null;
    }

    public function findInternalId(string $externalId, string $context): ?string
    {
        $result = $this->securityConnection->fetchOne(
            'SELECT internal_id FROM security.identity_mapping WHERE external_id = ? AND context = ?',
            [$externalId, $context],
        );

        return \is_string($result) ? $result : null;
    }
}
