<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

interface RatePeriodIdGeneratorInterface
{
    public function generate(): string;
}
