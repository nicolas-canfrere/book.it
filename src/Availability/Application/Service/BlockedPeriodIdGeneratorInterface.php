<?php

declare(strict_types=1);

namespace App\Availability\Application\Service;

interface BlockedPeriodIdGeneratorInterface
{
    public function generate(): string;
}
