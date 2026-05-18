<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface BookerExistsInterface
{
    public function exists(string $bookerId): bool;
}
