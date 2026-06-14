<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\AvailableRoomPickerInterface;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Room\Application\Contract\RoomsByTypeFinderInterface;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class AvailableRoomPicker implements AvailableRoomPickerInterface
{
    public function __construct(
        private RoomsByTypeFinderInterface $roomsByTypeFinder,
        private RoomAvailabilityCheckerInterface $availabilityChecker,
    ) {
    }

    public function pick(RoomTypeId $roomTypeId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): ?RoomId
    {
        foreach ($this->roomsByTypeFinder->findByType($roomTypeId) as $roomId) {
            if ($this->availabilityChecker->isAvailable($roomId, $checkIn, $checkOut)) {
                return $roomId;
            }
        }

        return null;
    }
}
