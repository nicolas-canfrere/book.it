<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Persistence\Doctrine;

use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Shared\Domain\ValueObject\RoomId;
use Doctrine\DBAL\Connection;

final readonly class AvailabilityHoldRepository implements AvailabilityHoldRepositoryInterface
{
    public function __construct(private Connection $availabilityConnection)
    {
    }

    public function add(AvailabilityHold $hold): void
    {
        $this->availabilityConnection->insert('hold', [
            'id' => $hold->id->value,
            'room_id' => $hold->roomId->value,
            'reservation_id' => $hold->reservationId,
            'check_in' => $hold->period->checkIn->format('Y-m-d'),
            'check_out' => $hold->period->checkOut->format('Y-m-d'),
            'expires_at' => $hold->expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $hold->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function deleteByReservationId(string $reservationId): void
    {
        $this->availabilityConnection->delete('hold', ['reservation_id' => $reservationId]);
    }

    public function hasActiveOverlap(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        $count = $this->availabilityConnection->fetchOne(
            'SELECT COUNT(*) FROM hold
             WHERE room_id = :roomId
               AND check_in < :checkOut
               AND check_out > :checkIn
               AND expires_at > :now',
            [
                'roomId' => $roomId->value,
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
                'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );

        return $count > 0;
    }
}
