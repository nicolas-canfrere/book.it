<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\AssignAdminRoleToOperator;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\OperatorId;

final readonly class AssignAdminRoleToOperatorCommand implements SyncCommandInterface
{
    public function __construct(public OperatorId $operatorId)
    {
    }
}
