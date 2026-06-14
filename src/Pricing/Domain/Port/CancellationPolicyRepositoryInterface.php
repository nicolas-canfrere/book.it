<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

use App\Pricing\Domain\Model\CancellationPolicy;
use App\Shared\Domain\ValueObject\RoomId;

interface CancellationPolicyRepositoryInterface
{
    public function findByRoomId(RoomId $roomId): ?CancellationPolicy;

    public function save(CancellationPolicy $policy): void;

    public function deleteByRoomId(RoomId $roomId): void;
}
