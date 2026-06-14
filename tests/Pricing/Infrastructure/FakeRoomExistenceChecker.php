<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure;

use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Shared\Domain\ValueObject\RoomId;

final class FakeRoomExistenceChecker implements RoomExistsInterface
{
    private bool $exists = true;

    public function setExists(bool $exists): void
    {
        $this->exists = $exists;
    }

    public function exists(RoomId $roomId): bool
    {
        return $this->exists;
    }
}
