<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Contract;

use App\Booker\Domain\Exception\ExternalAccountCreationException;
use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;

final readonly class SecurityAccountRegistrarAdapter implements ExternalAccountRegistrarInterface
{
    public function __construct(
        private AccountRegistrarInterface $accountRegistrar,
    ) {
    }

    public function register(string $bookerId, string $email, string $password): void
    {
        try {
            $this->accountRegistrar->register($bookerId, 'booker', $email, $password);
        } catch (AccountRegistrationFailedException $e) {
            throw new ExternalAccountCreationException($email, $e);
        }
    }

    public function unregister(string $bookerId): void
    {
        $this->accountRegistrar->unregister($bookerId, 'booker');
    }
}
