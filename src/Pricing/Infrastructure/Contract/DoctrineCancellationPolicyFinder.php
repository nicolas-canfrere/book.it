<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\CancellationPolicyFinderInterface;
use App\Pricing\Application\Contract\CancellationPolicyView;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;

final readonly class DoctrineCancellationPolicyFinder implements CancellationPolicyFinderInterface
{
    public function __construct(private CancellationPolicyRepositoryInterface $cancellationPolicies)
    {
    }

    public function find(string $roomId): ?CancellationPolicyView
    {
        $policy = $this->cancellationPolicies->findByRoomId($roomId);

        if (null === $policy) {
            return null;
        }

        return new CancellationPolicyView(daysThreshold: $policy->daysThreshold);
    }
}
