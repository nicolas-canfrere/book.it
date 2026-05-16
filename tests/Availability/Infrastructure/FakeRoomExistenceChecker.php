<?php

declare(strict_types=1);

namespace App\Tests\Availability\Infrastructure;

use App\Availability\Domain\Port\RoomExistsInterface;

final class FakeRoomExistenceChecker implements RoomExistsInterface
{
    private bool $exists = true;

    public function setExists(bool $exists): void
    {
        $this->exists = $exists;
    }

    public function exists(string $roomId): bool
    {
        return $this->exists;
    }
}
