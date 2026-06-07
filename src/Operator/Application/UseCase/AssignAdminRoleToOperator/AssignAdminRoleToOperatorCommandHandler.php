<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\AssignAdminRoleToOperator;

use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class AssignAdminRoleToOperatorCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ExternalAccountRegistrarInterface $accountRegistrar,
    ) {
    }

    public function __invoke(AssignAdminRoleToOperatorCommand $command): void
    {
        $this->accountRegistrar->assignAdminRole($command->operatorId);
    }
}
