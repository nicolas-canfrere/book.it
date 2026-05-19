<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\ValueObject\CancellationTerms;

interface CancellationPolicyFetcherInterface
{
    public function fetch(string $roomId): CancellationTerms;
}
