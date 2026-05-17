<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetBaseRate;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetBaseRateQuery implements SyncQueryInterface
{
    public function __construct(public string $roomId)
    {
    }
}
