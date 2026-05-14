<?php

declare(strict_types=1);

namespace App\Hotel\Application\Service;

interface HotelIdGeneratorInterface
{
    public function generate(): string;
}
