<?php

declare(strict_types=1);

namespace App\Tests\Operator\Application\UseCase\RegisterOperator;

use App\Operator\Application\UseCase\RegisterOperator\RegisterOperatorCommand;
use App\Operator\Application\UseCase\RegisterOperator\RegisterOperatorCommandHandler;
use App\Operator\Domain\Exception\OperatorAlreadyExistsException;
use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[Group('unit')]
final class RegisterOperatorCommandHandlerTest extends TestCase
{
    #[Test]
    public function it_throws_already_exists_before_calling_keycloak(): void
    {
        $repository = $this->createStub(OperatorRepositoryInterface::class);
        $repository->method('existsByEmail')->willReturn(true);

        /** @var ExternalAccountRegistrarInterface&MockObject $accountRegistrar */
        $accountRegistrar = $this->createMock(ExternalAccountRegistrarInterface::class);
        $accountRegistrar->expects(self::never())->method('register');

        $handler = new RegisterOperatorCommandHandler($repository, $accountRegistrar, new NullLogger());

        $this->expectException(OperatorAlreadyExistsException::class);
        ($handler)($this->makeCommand());
    }

    #[Test]
    public function it_compensates_by_unregistering_when_db_save_fails(): void
    {
        $repository = $this->createStub(OperatorRepositoryInterface::class);
        $repository->method('existsByEmail')->willReturn(false);
        $repository->method('add')->willThrowException(new \RuntimeException('DB error'));

        /** @var ExternalAccountRegistrarInterface&MockObject $accountRegistrar */
        $accountRegistrar = $this->createMock(ExternalAccountRegistrarInterface::class);
        $accountRegistrar->expects(self::once())->method('register');
        $accountRegistrar->expects(self::once())->method('unregister')->with('uuid-1');

        $handler = new RegisterOperatorCommandHandler($repository, $accountRegistrar, new NullLogger());

        $this->expectException(\RuntimeException::class);
        ($handler)($this->makeCommand());
    }

    #[Test]
    public function it_registers_external_account_then_saves_operator(): void
    {
        /** @var OperatorRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(OperatorRepositoryInterface::class);
        $repository->method('existsByEmail')->willReturn(false);
        $repository->expects(self::once())->method('add');

        /** @var ExternalAccountRegistrarInterface&MockObject $accountRegistrar */
        $accountRegistrar = $this->createMock(ExternalAccountRegistrarInterface::class);
        $accountRegistrar->expects(self::once())
            ->method('register')
            ->with('uuid-1', 'alice@hotel.com', 'password123');
        $accountRegistrar->expects(self::never())->method('unregister');

        $handler = new RegisterOperatorCommandHandler($repository, $accountRegistrar, new NullLogger());

        ($handler)($this->makeCommand());
    }

    private function makeCommand(
        string $email = 'alice@hotel.com',
        string $id = 'uuid-1',
    ): RegisterOperatorCommand {
        return new RegisterOperatorCommand(
            $id,
            'Alice',
            'Martin',
            $email,
            '+33612345678',
            'password123',
            new \DateTimeImmutable('2026-01-01'),
        );
    }
}
