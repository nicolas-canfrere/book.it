<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetBlockedPeriod;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\BlockedPeriodId;

/** @implements SyncQueryInterface<BlockedPeriod|null> */
final readonly class GetBlockedPeriodQuery implements SyncQueryInterface
{
    public function __construct(public BlockedPeriodId $id)
    {
    }
}
