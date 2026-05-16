<?php

declare(strict_types=1);

namespace App\Tests\Booker\Infrastructure\Persistence\InMemory;

use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;

final class InMemoryBookerRepository implements BookerRepositoryInterface
{
    /** @var array<string, Booker> */
    private array $bookers = [];

    public function add(Booker $booker): void
    {
        $this->bookers[$booker->id] = $booker;
    }

    public function get(string $id): ?Booker
    {
        return $this->bookers[$id] ?? null;
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
