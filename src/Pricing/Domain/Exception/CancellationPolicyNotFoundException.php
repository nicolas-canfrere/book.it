<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class CancellationPolicyNotFoundException extends \RuntimeException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Cancellation policy not found for room "%s".', $roomId));
    }
}
