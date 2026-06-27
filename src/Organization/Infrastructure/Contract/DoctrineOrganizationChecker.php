<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Contract;

use App\Organization\Application\Contract\OrganizationCheckerInterface;
use App\Organization\Domain\Model\OrganizationStatus;
use Doctrine\DBAL\Connection;

final readonly class DoctrineOrganizationChecker implements OrganizationCheckerInterface
{
    public function __construct(private Connection $organizationConnection)
    {
    }

    public function exists(string $organizationId): bool
    {
        /** @var int|string $count */
        $count = $this->organizationConnection->fetchOne(
            'SELECT COUNT(*) FROM organizations WHERE id = :id',
            ['id' => $organizationId],
        );

        return (int) $count > 0;
    }

    public function isActive(string $organizationId): bool
    {
        /** @var int|string $count */
        $count = $this->organizationConnection->fetchOne(
            'SELECT COUNT(*) FROM organizations WHERE id = :id AND status = :status',
            ['id' => $organizationId, 'status' => OrganizationStatus::Active->value],
        );

        return (int) $count > 0;
    }
}
