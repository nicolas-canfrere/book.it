<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Shared\Domain\ValueObject\GuestId;

interface GuestIdGeneratorInterface
{
    public function generate(): GuestId;
}
