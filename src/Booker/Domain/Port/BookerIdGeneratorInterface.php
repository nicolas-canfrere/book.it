<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

use App\Shared\Domain\ValueObject\BookerId;

interface BookerIdGeneratorInterface
{
    public function generate(): BookerId;
}
