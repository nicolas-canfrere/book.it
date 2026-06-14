<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

use App\Booker\Domain\Model\Booker;
use App\Shared\Domain\ValueObject\BookerId;

interface BookerRepositoryInterface
{
    public function add(Booker $booker): void;

    public function get(BookerId $id): ?Booker;

    public function existsByEmail(string $email): bool;
}
