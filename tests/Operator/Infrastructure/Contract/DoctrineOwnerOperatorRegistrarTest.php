<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\Contract;

use App\Operator\Application\Contract\OwnerOperatorRegistrarInterface;
use App\Operator\Domain\Exception\OperatorAlreadyExistsException;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use App\Operator\Infrastructure\Contract\DoctrineOwnerOperatorRegistrar;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Tests\Operator\Infrastructure\Persistence\InMemory\InMemoryOperatorRepository;
use App\Tests\Security\Infrastructure\NullAccountRegistrar;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[Group('unit')]
final class DoctrineOwnerOperatorRegistrarTest extends TestCase
{
    private AccountRegistrarInterface&MockObject $accountRegistrar;
    private InMemoryOperatorRepository $operatorRepository;
    private DoctrineOwnerOperatorRegistrar $registrar;

    protected function setUp(): void
    {
        $this->accountRegistrar = $this->createMock(AccountRegistrarInterface::class);
        $this->operatorRepository = new InMemoryOperatorRepository();
        $this->registrar = new DoctrineOwnerOperatorRegistrar(
            $this->operatorRepository,
            $this->accountRegistrar,
            new NullLogger(),
        );
    }

    #[Test]
    public function itThrowsWhenEmailAlreadyExists(): void
    {
        $this->operatorRepository->add(new \App\Operator\Domain\Model\Operator(
            new \App\Shared\Domain\ValueObject\OperatorId('existing-op-id'),
            'Bob', 'Dupont', 'owner@hotel.com', '+33600000001',
            new \DateTimeImmutable(),
            new \App\Shared\Domain\ValueObject\OrganizationId('some-org-id'),
            \App\Operator\Domain\ValueObject\OperatorRole::Owner,
        ));

        $this->accountRegistrar->expects(self::never())->method('register');

        $this->expectException(OperatorAlreadyExistsException::class);
        $this->callRegisterOwner('owner@hotel.com');
    }

    #[Test]
    public function itCreatesKeycloakAccountSetsOrgIdAndSavesOperator(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('register')
            ->with('op-uuid', 'operator', 'alice@hotel.com', 'Passw0rd!');

        $this->accountRegistrar->expects(self::once())
            ->method('setOrganizationId')
            ->with('op-uuid', 'operator', 'org-uuid');

        $this->accountRegistrar->expects(self::never())->method('unregister');

        $this->callRegisterOwner('alice@hotel.com');

        $operator = $this->operatorRepository->findByEmail('alice@hotel.com');
        self::assertNotNull($operator);
        self::assertSame('Alice', $operator->firstName);
        self::assertSame(\App\Operator\Domain\ValueObject\OperatorRole::Owner, $operator->role);
        self::assertSame('org-uuid', $operator->organizationId->value);
    }

    #[Test]
    public function itCompensatesKeycloakAccountWhenDbSaveFails(): void
    {
        $throwingRepository = $this->createMock(OperatorRepositoryInterface::class);
        $throwingRepository->method('existsByEmail')->willReturn(false);
        $throwingRepository->method('add')->willThrowException(new \RuntimeException('DB down'));

        $registrar = new DoctrineOwnerOperatorRegistrar(
            $throwingRepository,
            $this->accountRegistrar,
            new NullLogger(),
        );

        $this->accountRegistrar->expects(self::once())->method('register');
        $this->accountRegistrar->expects(self::once())->method('setOrganizationId');
        $this->accountRegistrar->expects(self::once())
            ->method('unregister')
            ->with('op-uuid', 'operator');

        $this->expectException(\RuntimeException::class);
        $registrar->registerOwner(
            'op-uuid', 'Alice', 'Martin', 'alice@hotel.com', '+33612345678',
            'Passw0rd!', 'org-uuid', new \DateTimeImmutable(),
        );
    }

    private function callRegisterOwner(string $email): void
    {
        $this->registrar->registerOwner(
            'op-uuid', 'Alice', 'Martin', $email, '+33612345678',
            'Passw0rd!', 'org-uuid', new \DateTimeImmutable('2026-06-28T10:00:00Z'),
        );
    }
}
