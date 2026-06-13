<?php

declare(strict_types=1);

namespace App\Tests\Booker\Application\UseCase\RegisterBookerWithCredentials;

use App\Booker\Application\UseCase\RegisterBookerWithCredentials\RegisterBookerWithCredentialsCommand;
use App\Booker\Application\UseCase\RegisterBookerWithCredentials\RegisterBookerWithCredentialsCommandHandler;
use App\Booker\Domain\Exception\BookerAlreadyExistsException;
use App\Booker\Domain\Exception\BookerUnderageException;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;
use App\Shared\Domain\ValueObject\BookerId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[Group('unit')]
final class RegisterBookerWithCredentialsCommandHandlerTest extends TestCase
{
    private BookerRepositoryInterface&MockObject $repository;
    private ExternalAccountRegistrarInterface&MockObject $accountRegistrar;
    private RegisterBookerWithCredentialsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(BookerRepositoryInterface::class);
        $this->accountRegistrar = $this->createMock(ExternalAccountRegistrarInterface::class);
        $this->handler = new RegisterBookerWithCredentialsCommandHandler(
            $this->repository,
            $this->accountRegistrar,
            new NullLogger(),
        );
    }

    #[Test]
    public function it_throws_underage_exception_before_calling_keycloak(): void
    {
        $command = $this->makeCommand(dateOfBirth: '2010-01-01', registeredAt: '2025-01-01');
        $this->accountRegistrar->expects(self::never())->method('register');

        $this->expectException(BookerUnderageException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function it_throws_already_exists_before_calling_keycloak(): void
    {
        $this->repository->method('existsByEmail')->willReturn(true);
        $command = $this->makeCommand();
        $this->accountRegistrar->expects(self::never())->method('register');

        $this->expectException(BookerAlreadyExistsException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function it_compensates_by_unregistering_when_db_save_fails(): void
    {
        $this->repository->method('existsByEmail')->willReturn(false);
        $this->repository->method('add')->willThrowException(new \RuntimeException('DB error'));
        $command = $this->makeCommand();

        $this->accountRegistrar->expects(self::once())->method('register');
        $this->accountRegistrar->expects(self::once())->method('unregister')->with(new BookerId('uuid-1'));

        $this->expectException(\RuntimeException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function it_registers_external_account_then_saves_booker(): void
    {
        $this->repository->method('existsByEmail')->willReturn(false);
        $command = $this->makeCommand();

        $this->accountRegistrar->expects(self::once())
            ->method('register')
            ->with(new BookerId('uuid-1'), 'jean@example.com', 'password123');
        $this->accountRegistrar->expects(self::never())->method('unregister');
        $this->repository->expects(self::once())->method('add');

        ($this->handler)($command);
    }

    private function makeCommand(
        string $dateOfBirth = '1990-01-01',
        string $registeredAt = '2025-01-01',
        string $email = 'jean@example.com',
        string $id = 'uuid-1',
    ): RegisterBookerWithCredentialsCommand {
        return new RegisterBookerWithCredentialsCommand(
            new BookerId($id),
            'Jean',
            'Dupont',
            $email,
            '+33612345678',
            new \DateTimeImmutable($dateOfBirth),
            'password123',
            new \DateTimeImmutable($registeredAt),
        );
    }
}
