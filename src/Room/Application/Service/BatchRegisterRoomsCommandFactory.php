<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\UseCase\BatchRegisterRooms\BatchRegisterRoomsCommand;
use App\Room\Domain\Port\RoomIdGeneratorInterface;
use Psr\Clock\ClockInterface;

final readonly class BatchRegisterRoomsCommandFactory
{
    public function __construct(
        private RoomIdGeneratorInterface $roomIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    /** @param list<RoomCsvRow> $rows */
    public function create(string $hotelId, string $roomTypeId, array $rows): BatchRegisterRoomsCommand
    {
        $entries = array_map(
            fn(RoomCsvRow $row) => [
                'id' => $this->roomIdGenerator->generate(),
                'number' => trim($row->number),
                'floor' => $row->floor,
            ],
            $rows,
        );

        return new BatchRegisterRoomsCommand($hotelId, $roomTypeId, $entries, $this->clock->now());
    }
}
