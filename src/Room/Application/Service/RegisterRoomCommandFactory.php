<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommand;
use App\Room\Domain\Port\RoomIdGeneratorInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Psr\Clock\ClockInterface;

final readonly class RegisterRoomCommandFactory
{
    public function __construct(
        private RoomIdGeneratorInterface $roomIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $hotelId, ?string $number, ?int $floor, ?string $roomTypeId): RegisterRoomCommand
    {
        if (null === $number) {
            throw new \InvalidArgumentException('Room number is required.');
        }
        if (null === $floor) {
            throw new \InvalidArgumentException('Room floor is required.');
        }
        if (null === $roomTypeId) {
            throw new \InvalidArgumentException('Room type ID is required.');
        }

        return new RegisterRoomCommand(
            $this->roomIdGenerator->generate(),
            $hotelId,
            $number,
            $floor,
            new RoomTypeId($roomTypeId),
            $this->clock->now(),
        );
    }
}
