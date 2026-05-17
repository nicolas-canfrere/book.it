<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetBaseRate;

use App\Pricing\Domain\Model\BaseRate;
use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<BaseRate> */
final readonly class GetBaseRateQuery implements SyncQueryInterface
{
    public function __construct(public string $roomId)
    {
    }
}
