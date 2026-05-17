<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetBlockedPeriod;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<BlockedPeriod|null> */
final readonly class GetBlockedPeriodQuery implements SyncQueryInterface
{
    public function __construct(public string $id)
    {
    }
}
