<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetBlockedPeriod;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetBlockedPeriodQuery implements SyncQueryInterface
{
    public function __construct(public string $id)
    {
    }
}
