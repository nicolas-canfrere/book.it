<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Shared\Application\Transaction\TransactionManagerInterface;

final class FakeTransactionManager implements TransactionManagerInterface
{
    public function transactional(callable $callback): void
    {
        $callback();
    }
}
