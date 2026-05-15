<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\UseCase\BatchRegisterRooms\BatchRegisterRoomsCommand;
use Psr\Clock\ClockInterface;

final readonly class BatchRegisterRoomsCommandFactory
{
    public function __construct(
        private RoomIdGeneratorInterface $roomIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    /** @param list<string> $numbers */
    public function create(string $hotelId, array $numbers): BatchRegisterRoomsCommand
    {
        $entries = array_map(
            fn(string $number) => ['id' => $this->roomIdGenerator->generate(), 'number' => trim($number)],
            $numbers,
        );

        return new BatchRegisterRoomsCommand($hotelId, $entries, $this->clock->now());
    }
}
