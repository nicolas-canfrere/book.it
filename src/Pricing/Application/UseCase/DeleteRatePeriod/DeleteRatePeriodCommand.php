<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeleteRatePeriod;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeleteRatePeriodCommand implements SyncCommandInterface
{
    public function __construct(
        public string $ratePeriodId,
    ) {
    }
}
