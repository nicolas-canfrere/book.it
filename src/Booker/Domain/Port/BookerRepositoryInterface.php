<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

use App\Booker\Domain\Model\Booker;

interface BookerRepositoryInterface
{
    public function add(Booker $booker): void;

    public function get(string $id): ?Booker;

    public function existsByEmail(string $email): bool;
}
