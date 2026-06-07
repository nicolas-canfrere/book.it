<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Contract;

use App\Operator\Domain\Exception\ExternalAccountCreationException;
use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;

final readonly class SecurityAccountRegistrarAdapter implements ExternalAccountRegistrarInterface
{
    public function __construct(
        private AccountRegistrarInterface $accountRegistrar,
    ) {
    }

    public function register(string $operatorId, string $email, string $password): void
    {
        try {
            $this->accountRegistrar->register($operatorId, 'operator', $email, $password);
        } catch (AccountRegistrationFailedException $e) {
            throw new ExternalAccountCreationException($email, $e);
        }
    }

    public function unregister(string $operatorId): void
    {
        $this->accountRegistrar->unregister($operatorId, 'operator');
    }

    public function assignAdminRole(string $operatorId): void
    {
        $this->accountRegistrar->assignRole($operatorId, 'operator', 'ROLE_ADMIN');
    }
}
