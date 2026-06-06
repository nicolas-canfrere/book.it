<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Persistence;

use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class IdentityMappingRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private IdentityMappingRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->repository = new IdentityMappingRepository($this->connection);
    }

    #[Test]
    public function it_saves_mapping(): void
    {
        $this->connection->expects(self::once())
            ->method('insert')
            ->with('security.identity_mapping', [
                'internal_id' => 'booker-uuid',
                'context' => 'booker',
                'external_id' => 'keycloak-uuid',
            ]);

        $this->repository->save('booker-uuid', 'booker', 'keycloak-uuid');
    }

    #[Test]
    public function it_deletes_mapping(): void
    {
        $this->connection->expects(self::once())
            ->method('delete')
            ->with('security.identity_mapping', [
                'internal_id' => 'booker-uuid',
                'context' => 'booker',
            ]);

        $this->repository->delete('booker-uuid', 'booker');
    }

    #[Test]
    public function it_finds_external_id(): void
    {
        $this->connection->expects(self::once())
            ->method('fetchOne')
            ->with(
                'SELECT external_id FROM security.identity_mapping WHERE internal_id = ? AND context = ?',
                ['booker-uuid', 'booker'],
            )
            ->willReturn('keycloak-uuid');

        self::assertSame('keycloak-uuid', $this->repository->findExternalId('booker-uuid', 'booker'));
    }

    #[Test]
    public function it_returns_null_when_mapping_not_found(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        self::assertNull($this->repository->findExternalId('booker-uuid', 'booker'));
    }
}
