<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteAvailabilityHold;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeleteAvailabilityHoldCommand implements SyncCommandInterface
{
    public function __construct(public string $reservationId)
    {
    }
}
