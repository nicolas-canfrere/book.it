<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class RemoveSearchUnavailablePeriodBySourceCommand implements AsyncCommandInterface
{
    public function __construct(public string $sourceId)
    {
    }
}
