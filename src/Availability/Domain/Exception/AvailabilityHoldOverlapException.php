<?php

declare(strict_types=1);

namespace App\Availability\Domain\Exception;

final class AvailabilityHoldOverlapException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('An active availability hold already exists for this room and period.');
    }
}
