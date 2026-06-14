<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomExistsInterface;
use App\Room\Application\Contract\RoomFinderInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class RoomExistenceChecker implements RoomExistsInterface
{
    public function __construct(private RoomFinderInterface $rooms)
    {
    }

    public function exists(RoomId $roomId): bool
    {
        return null !== $this->rooms->find($roomId->value);
    }
}
