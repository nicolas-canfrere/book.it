<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\UseCase\BatchRegisterRooms\BatchRegisterRoomsCommand;
use App\Room\Domain\Port\RoomIdGeneratorInterface;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Psr\Clock\ClockInterface;

final readonly class BatchRegisterRoomsCommandFactory
{
    public function __construct(
        private RoomIdGeneratorInterface $roomIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    /** @param list<RoomCsvRow> $rows */
    public function create(string $hotelId, array $rows): BatchRegisterRoomsCommand
    {
        $entries = array_map(
            fn(RoomCsvRow $row) => [
                'id' => $this->roomIdGenerator->generate(),
                'number' => trim($row->number),
                'floor' => $row->floor,
                'roomTypeId' => new RoomTypeId($row->roomTypeId),
            ],
            $rows,
        );

        return new BatchRegisterRoomsCommand(new HotelId($hotelId), $entries, $this->clock->now());
    }
}
