<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteAvailabilityHold;

final readonly class DeleteAvailabilityHoldCommand
{
    public function __construct(public string $reservationId)
    {
    }
}
