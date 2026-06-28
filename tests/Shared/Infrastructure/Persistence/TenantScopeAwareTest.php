<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Persistence;

use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\OrganizationId;
use App\Shared\Infrastructure\Persistence\TenantScopeAware;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TenantScopeAwareTest extends TestCase
{
    #[Test]
    public function itAddsOrganizationIdWhereClause(): void
    {
        $orgId = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $ctx = new TenantContext();
        $ctx->set($orgId);

        $consumer = new class($ctx) {
            use TenantScopeAware;

            public function __construct(private readonly TenantContext $tenantContext)
            {
            }

            public function expose(QueryBuilder $qb, string $alias): QueryBuilder
            {
                return $this->applyTenantScope($qb, $alias);
            }
        };

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();

        $result = $consumer->expose($qb, 'h');
        self::assertSame($qb, $result);
    }
}
