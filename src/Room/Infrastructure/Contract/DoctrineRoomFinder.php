<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Contract;

use App\Room\Application\Contract\RoomFinderInterface;
use App\Room\Application\Contract\RoomView;
use App\Room\Domain\Port\RoomCapacityFinderInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class DoctrineRoomFinder implements RoomFinderInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private RoomCapacityFinderInterface $capacityFinder,
    ) {
    }

    public function find(string $roomId): ?RoomView
    {
        $id = new RoomId($roomId);
        $room = $this->roomRepository->get($id);

        if (null === $room) {
            return null;
        }

        return new RoomView(
            id: $room->id->value,
            capacity: $this->capacityFinder->findCapacity($id),
        );
    }
}
