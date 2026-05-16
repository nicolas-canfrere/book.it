<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeleteBlockedPeriodCommand implements SyncCommandInterface
{
    public function __construct(public string $id)
    {
    }
}
