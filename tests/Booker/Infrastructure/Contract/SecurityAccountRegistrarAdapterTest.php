<?php

declare(strict_types=1);

namespace App\Tests\Booker\Infrastructure\Contract;

use App\Booker\Domain\Exception\ExternalAccountCreationException;
use App\Booker\Infrastructure\Contract\SecurityAccountRegistrarAdapter;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SecurityAccountRegistrarAdapterTest extends TestCase
{
    private AccountRegistrarInterface&MockObject $accountRegistrar;
    private SecurityAccountRegistrarAdapter $adapter;

    protected function setUp(): void
    {
        $this->accountRegistrar = $this->createMock(AccountRegistrarInterface::class);
        $this->adapter = new SecurityAccountRegistrarAdapter($this->accountRegistrar);
    }

    #[Test]
    public function it_delegates_register_with_booker_context(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('register')
            ->with('booker-id', 'booker', 'email@example.com', 'password');

        $this->adapter->register('booker-id', 'email@example.com', 'password');
    }

    #[Test]
    public function it_delegates_unregister_with_booker_context(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('unregister')
            ->with('booker-id', 'booker');

        $this->adapter->unregister('booker-id');
    }

    #[Test]
    public function it_wraps_account_registration_failed_as_external_account_creation_exception(): void
    {
        $this->accountRegistrar->method('register')
            ->willThrowException(new AccountRegistrationFailedException('email@example.com'));

        $this->expectException(ExternalAccountCreationException::class);
        $this->adapter->register('booker-id', 'email@example.com', 'password');
    }
}
