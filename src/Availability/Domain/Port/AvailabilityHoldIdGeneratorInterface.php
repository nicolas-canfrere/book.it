<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

interface AvailabilityHoldIdGeneratorInterface
{
    public function generate(): string;
}
