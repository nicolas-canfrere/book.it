<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

use App\Shared\Domain\ValueObject\OperatorId;

interface OperatorIdGeneratorInterface
{
    public function generate(): OperatorId;
}
