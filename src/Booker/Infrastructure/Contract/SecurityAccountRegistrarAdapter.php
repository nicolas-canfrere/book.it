<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Contract;

use App\Booker\Domain\Exception\ExternalAccountCreationException;
use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Shared\Domain\ValueObject\BookerId;

final readonly class SecurityAccountRegistrarAdapter implements ExternalAccountRegistrarInterface
{
    public function __construct(
        private AccountRegistrarInterface $accountRegistrar,
    ) {
    }

    public function register(BookerId $bookerId, string $email, string $password): void
    {
        try {
            $this->accountRegistrar->register((string) $bookerId, 'booker', $email, $password);
        } catch (AccountRegistrationFailedException $e) {
            throw new ExternalAccountCreationException($email, $e);
        }
    }

    public function unregister(BookerId $bookerId): void
    {
        $this->accountRegistrar->unregister((string) $bookerId, 'booker');
    }
}
