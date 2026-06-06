<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;

final readonly class IdentityMappingRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function save(string $internalId, string $context, string $externalId): void
    {
        $this->connection->insert('security.identity_mapping', [
            'internal_id' => $internalId,
            'context' => $context,
            'external_id' => $externalId,
        ]);
    }

    public function delete(string $internalId, string $context): void
    {
        $this->connection->delete('security.identity_mapping', [
            'internal_id' => $internalId,
            'context' => $context,
        ]);
    }

    public function findExternalId(string $internalId, string $context): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT external_id FROM security.identity_mapping WHERE internal_id = ? AND context = ?',
            [$internalId, $context],
        );

        return false !== $result ? (string) $result : null;
    }
}
