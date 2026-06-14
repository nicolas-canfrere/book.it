<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Shared\Domain\ValueObject\AvailabilityHoldId;

interface AvailabilityHoldIdGeneratorInterface
{
    public function generate(): AvailabilityHoldId;
}
