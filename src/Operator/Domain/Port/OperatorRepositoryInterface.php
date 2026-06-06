<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

use App\Operator\Domain\Model\Operator;

interface OperatorRepositoryInterface
{
    public function add(Operator $operator): void;

    public function existsByEmail(string $email): bool;
}
