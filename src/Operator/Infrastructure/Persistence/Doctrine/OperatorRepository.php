<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Persistence\Doctrine;

use App\Operator\Domain\Model\Operator;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use App\Shared\Application\TenantContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

final class OperatorRepository implements OperatorRepositoryInterface
{
    private readonly TenantContext $tenantContext;

    public function __construct(
        private readonly Connection $operatorConnection,
        TenantContext $tenantContext,
    ) {
        $this->tenantContext = $tenantContext;
    }

    public function add(Operator $operator): void
    {
        $this->operatorConnection->insert('operator', [
            'id' => $operator->id->value,
            'first_name' => $operator->firstName,
            'last_name' => $operator->lastName,
            'email' => $operator->email,
            'phone' => $operator->phone,
            'registered_at' => $operator->registeredAt->format('Y-m-d H:i:s'),
            'organization_id' => $operator->organizationId->value,
            'role' => $operator->role->value,
        ]);
    }

    public function existsByEmail(string $email): bool
    {
        $qb = $this->operatorConnection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('operator', 'o')
            ->where('LOWER(o.email) = LOWER(:email)')
            ->setParameter('email', $email);

        if ($this->tenantContext->isInitialized()) {
            $this->applyTenantScope($qb, 'o');
        }

        /** @var int|string $count */
        $count = $qb->fetchOne();

        return (int) $count > 0;
    }

    private function applyTenantScope(QueryBuilder $qb, string $tableAlias = 't'): void
    {
        $qb
            ->andWhere("{$tableAlias}.organization_id = :tenant_id")
            ->setParameter('tenant_id', $this->tenantContext->getOrganizationId()->value);
    }
}
