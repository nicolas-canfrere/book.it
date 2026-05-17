<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

interface BookerIdGeneratorInterface
{
    public function generate(): string;
}
