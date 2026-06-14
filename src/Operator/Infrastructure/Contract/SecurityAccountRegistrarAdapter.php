<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Contract;

use App\Operator\Domain\Exception\ExternalAccountCreationException;
use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Shared\Domain\ValueObject\OperatorId;

final readonly class SecurityAccountRegistrarAdapter implements ExternalAccountRegistrarInterface
{
    public function __construct(
        private AccountRegistrarInterface $accountRegistrar,
    ) {
    }

    public function register(OperatorId $operatorId, string $email, string $password): void
    {
        try {
            $this->accountRegistrar->register($operatorId->value, 'operator', $email, $password);
        } catch (AccountRegistrationFailedException $e) {
            throw new ExternalAccountCreationException($email, $e);
        }
    }

    public function unregister(OperatorId $operatorId): void
    {
        $this->accountRegistrar->unregister($operatorId->value, 'operator');
    }

    public function assignAdminRole(OperatorId $operatorId): void
    {
        $this->accountRegistrar->assignRole($operatorId->value, 'operator', 'ROLE_ADMIN');
    }
}
