<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\BatchRegisterRooms;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomBatchInvalidException;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class BatchRegisterRoomsCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private HotelExistsInterface $hotelExists,
    ) {
    }

    public function __invoke(BatchRegisterRoomsCommand $command): void
    {
        if (!$this->hotelExists->exists($command->hotelId)) {
            throw new HotelNotFoundException($command->hotelId);
        }

        $violations = [];
        $seenNumbers = [];

        foreach ($command->entries as $index => $entry) {
            $lineField = \sprintf('line[%d]', $index + 2);
            $number = $entry['number'];
            $floor = $entry['floor'];

            if ('' === $number) {
                $violations[] = ['field' => $lineField, 'message' => 'Room number must not be blank.'];
                continue;
            }

            if (mb_strlen($number) > 50) {
                $violations[] = ['field' => $lineField, 'message' => 'Room number must not exceed 50 characters.'];
                continue;
            }

            if ($floor < -20 || $floor > 300) {
                $violations[] = ['field' => $lineField, 'message' => 'Room floor must be between -20 and 300.'];
                continue;
            }

            if (isset($seenNumbers[$number])) {
                $violations[] = ['field' => $lineField, 'message' => \sprintf('Room number "%s" is duplicated in this batch.', $number)];
                continue;
            }

            if ($this->roomRepository->existsByHotelIdAndNumber($command->hotelId, $number)) {
                $violations[] = ['field' => $lineField, 'message' => \sprintf('Room number "%s" already exists in this hotel.', $number)];
                continue;
            }

            $seenNumbers[$number] = true;
        }

        if ([] !== $violations) {
            throw new RoomBatchInvalidException($violations);
        }

        $rooms = array_map(
            fn(array $entry) => new Room(
                $entry['id'],
                $command->hotelId,
                new RoomNumber(trim($entry['number'])),
                new RoomFloor($entry['floor']),
                $command->createdAt,
            ),
            $command->entries,
        );

        $this->roomRepository->addAll($rooms);
    }
}
