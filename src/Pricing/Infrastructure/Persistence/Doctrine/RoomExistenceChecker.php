<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine;

use App\Pricing\Domain\Port\RoomExistsInterface;
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
