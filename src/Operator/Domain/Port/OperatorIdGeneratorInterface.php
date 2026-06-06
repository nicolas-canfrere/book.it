<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

interface OperatorIdGeneratorInterface
{
    public function generate(): string;
}
