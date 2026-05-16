<?php

declare(strict_types=1);

namespace App\Availability\Domain\Exception;

final class BlockedPeriodNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Blocked period "%s" not found.', $id));
    }
}
