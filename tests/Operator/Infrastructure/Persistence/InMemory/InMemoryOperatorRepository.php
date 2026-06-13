<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\Persistence\InMemory;

use App\Operator\Domain\Model\Operator;
use App\Operator\Domain\Port\OperatorRepositoryInterface;

final class InMemoryOperatorRepository implements OperatorRepositoryInterface
{
    /** @var array<string, Operator> */
    private array $operators = [];

    public function add(Operator $operator): void
    {
        $this->operators[$operator->id->value] = $operator;
    }

    public function existsByEmail(string $email): bool
    {
        foreach ($this->operators as $operator) {
            if (strtolower($operator->email) === strtolower($email)) {
                return true;
            }
        }

        return false;
    }
}
