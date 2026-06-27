<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\TenantContext;
use Doctrine\DBAL\Query\QueryBuilder;

trait TenantScopeAware
{
    private readonly TenantContext $tenantContext;

    private function applyTenantScope(QueryBuilder $qb, string $tableAlias = 't'): QueryBuilder
    {
        return $qb
            ->andWhere("{$tableAlias}.organization_id = :tenant_id")
            ->setParameter('tenant_id', $this->tenantContext->getOrganizationId()->value);
    }
}
