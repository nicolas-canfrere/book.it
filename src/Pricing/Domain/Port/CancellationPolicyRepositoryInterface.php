<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

use App\Pricing\Domain\Model\CancellationPolicy;

interface CancellationPolicyRepositoryInterface
{
    public function findByRoomId(string $roomId): ?CancellationPolicy;

    public function save(CancellationPolicy $policy): void;

    public function deleteByRoomId(string $roomId): void;
}
