<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Persistence\InMemory;

use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;

final class InMemoryCancellationPolicyRepository implements CancellationPolicyRepositoryInterface
{
    /** @var array<string, CancellationPolicy> */
    private array $policies = [];

    public function save(CancellationPolicy $policy): void
    {
        $this->policies[$policy->roomId] = $policy;
    }

    public function findByRoomId(string $roomId): ?CancellationPolicy
    {
        return $this->policies[$roomId] ?? null;
    }

    public function deleteByRoomId(string $roomId): void
    {
        unset($this->policies[$roomId]);
    }
}
