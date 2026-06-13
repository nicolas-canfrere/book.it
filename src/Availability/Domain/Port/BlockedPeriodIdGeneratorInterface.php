<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Shared\Domain\ValueObject\BlockedPeriodId;

interface BlockedPeriodIdGeneratorInterface
{
    public function generate(): BlockedPeriodId;
}
