<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class RatePeriodNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Rate period "%s" not found.', $id));
    }
}
