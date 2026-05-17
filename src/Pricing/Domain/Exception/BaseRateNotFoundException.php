<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class BaseRateNotFoundException extends \RuntimeException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Base rate for room "%s" not found.', $roomId));
    }
}
