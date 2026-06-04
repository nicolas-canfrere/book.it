<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

final readonly class CancellationPolicyView
{
    public function __construct(public int $daysThreshold)
    {
    }
}
