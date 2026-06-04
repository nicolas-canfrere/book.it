<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

interface CancellationPolicyFinderInterface
{
    public function find(string $roomId): ?CancellationPolicyView;
}
