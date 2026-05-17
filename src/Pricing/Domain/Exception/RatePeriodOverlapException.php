<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class RatePeriodOverlapException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The requested period overlaps with an existing rate period.');
    }
}
