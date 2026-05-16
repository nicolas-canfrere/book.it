<?php

declare(strict_types=1);

namespace App\Availability\Domain\Exception;

final class BlockedPeriodOverlapException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The requested period overlaps with an existing blocked period.');
    }
}
