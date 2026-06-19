<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Shared\Domain\ValueObject\BookerId;

interface BookerExistsInterface
{
    public function exists(BookerId $bookerId): bool;
}
