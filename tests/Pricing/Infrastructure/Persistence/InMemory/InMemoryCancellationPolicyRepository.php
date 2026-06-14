<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Persistence\InMemory;

use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Shared\Domain\ValueObject\RoomId;

final class InMemoryCancellationPolicyRepository implements CancellationPolicyRepositoryInterface
{
    /** @var array<string, CancellationPolicy> */
    private array $policies = [];

    public function save(CancellationPolicy $policy): void
    {
        $this->policies[$policy->roomId->value] = $policy;
    }

    public function findByRoomId(RoomId $roomId): ?CancellationPolicy
    {
        return $this->policies[$roomId->value] ?? null;
    }

    public function deleteByRoomId(RoomId $roomId): void
    {
        unset($this->policies[$roomId->value]);
    }
}
