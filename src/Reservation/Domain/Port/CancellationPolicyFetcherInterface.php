<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Shared\Domain\ValueObject\RoomId;

interface CancellationPolicyFetcherInterface
{
    public function fetch(RoomId $roomId): CancellationTerms;
}
