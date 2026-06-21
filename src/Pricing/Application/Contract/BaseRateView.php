<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

final readonly class BaseRateView
{
    public function __construct(public int $amountCents)
    {
    }
}
