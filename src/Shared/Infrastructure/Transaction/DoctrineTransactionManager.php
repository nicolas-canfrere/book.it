<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Transaction;

use App\Shared\Application\Transaction\TransactionManagerInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineTransactionManager implements TransactionManagerInterface
{
    public function __construct(private Connection $bookitConnection)
    {
    }

    public function transactional(callable $callback): void
    {
        $this->bookitConnection->transactional(static function () use ($callback): void {
            $callback();
        });
    }
}
