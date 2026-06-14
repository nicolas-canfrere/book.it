<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure;

use App\Room\Domain\Port\RoomTypeExistsInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;

final class FakeRoomTypeExistenceChecker implements RoomTypeExistsInterface
{
    private bool $roomTypeExists = true;

    public function setExists(bool $exists): void
    {
        $this->roomTypeExists = $exists;
    }

    public function exists(RoomTypeId $roomTypeId): bool
    {
        return $this->roomTypeExists;
    }
}
