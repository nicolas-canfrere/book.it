<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Persistence\Doctrine;

use App\Availability\Domain\Port\RoomExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;

final readonly class RoomExistenceChecker implements RoomExistsInterface
{
    public function __construct(private RoomRepositoryInterface $roomRepository)
    {
    }

    public function exists(string $roomId): bool
    {
        return null !== $this->roomRepository->get($roomId);
    }
}
