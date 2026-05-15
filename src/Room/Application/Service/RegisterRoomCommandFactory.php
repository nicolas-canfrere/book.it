<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommand;
use Psr\Clock\ClockInterface;

final readonly class RegisterRoomCommandFactory
{
    public function __construct(
        private RoomIdGeneratorInterface $roomIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $hotelId, ?string $number): RegisterRoomCommand
    {
        if (null === $number) {
            throw new \InvalidArgumentException('Room number is required.');
        }

        return new RegisterRoomCommand(
            $this->roomIdGenerator->generate(),
            $hotelId,
            $number,
            $this->clock->now(),
        );
    }
}
