<?php

declare(strict_types=1);

namespace App\Shared\Application\Transaction;

interface TransactionManagerInterface
{
    public function transactional(callable $callback): void;
}
