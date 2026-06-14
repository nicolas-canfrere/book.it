<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Persistence;

use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use App\Shared\Domain\ValueObject\RoomId;
use Doctrine\DBAL\Connection;

final readonly class UnavailablePeriodWriter implements UnavailablePeriodWriterInterface
{
    public function __construct(private Connection $searchConnection)
    {
    }

    public function add(
        string $sourceId,
        RoomId $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void {
        $roomRow = $this->searchConnection->fetchAssociative(
            'SELECT room_type_id, hotel_id FROM room_index WHERE room_id = :roomId',
            ['roomId' => $roomId->value],
        );

        if (false === $roomRow) {
            return;
        }

        $this->searchConnection->executeStatement(
            <<<'SQL'
            INSERT INTO unavailable_periods (id, room_id, room_type_id, hotel_id, period, source_id)
            VALUES (:id, :roomId, :roomTypeId, :hotelId, daterange(:checkIn, :checkOut), :sourceId)
            ON CONFLICT (id) DO NOTHING
            SQL,
            [
                'id' => $sourceId,
                'roomId' => $roomId->value,
                'roomTypeId' => $roomRow['room_type_id'],
                'hotelId' => $roomRow['hotel_id'],
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
                'sourceId' => $sourceId,
            ],
        );
    }

    public function removeByPeriod(
        RoomId $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void {
        $this->searchConnection->executeStatement(
            <<<'SQL'
            DELETE FROM unavailable_periods
            WHERE room_id = :roomId
              AND period = daterange(:checkIn, :checkOut)
            SQL,
            ['roomId' => $roomId->value, 'checkIn' => $checkIn->format('Y-m-d'), 'checkOut' => $checkOut->format('Y-m-d')],
        );
    }

    public function removeBySource(string $sourceId): void
    {
        $this->searchConnection->executeStatement(
            'DELETE FROM unavailable_periods WHERE source_id = :sourceId',
            ['sourceId' => $sourceId],
        );
    }
}
