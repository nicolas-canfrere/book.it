<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

interface HotelIdGeneratorInterface
{
    public function generate(): string;
}
