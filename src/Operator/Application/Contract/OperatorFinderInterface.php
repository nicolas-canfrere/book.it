<?php

declare(strict_types=1);

namespace App\Operator\Application\Contract;

interface OperatorFinderInterface
{
    public function find(string $operatorId): ?OperatorView;
}
