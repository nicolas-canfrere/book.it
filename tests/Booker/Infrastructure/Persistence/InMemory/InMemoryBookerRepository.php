<?php

declare(strict_types=1);

namespace App\Tests\Booker\Infrastructure\Persistence\InMemory;

use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Shared\Domain\ValueObject\BookerId;

final class InMemoryBookerRepository implements BookerRepositoryInterface
{
    /** @var array<string, Booker> */
    private array $bookers = [];

    public function add(Booker $booker): void
    {
        $this->bookers[$booker->id->value] = $booker;
    }

    public function get(BookerId $id): ?Booker
    {
        return $this->bookers[$id->value] ?? null;
    }

    public function existsByEmail(string $email): bool
    {
        foreach ($this->bookers as $booker) {
            if (strtolower($booker->email) === strtolower($email)) {
                return true;
            }
        }

        return false;
    }
}
