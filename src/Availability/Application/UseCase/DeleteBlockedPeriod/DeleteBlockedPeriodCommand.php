<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\BlockedPeriodId;

final readonly class DeleteBlockedPeriodCommand implements SyncCommandInterface
{
    public function __construct(public BlockedPeriodId $id)
    {
    }
}
