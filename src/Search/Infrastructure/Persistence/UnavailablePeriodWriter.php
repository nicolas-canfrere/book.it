<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Persistence;

use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use Doctrine\DBAL\Connection;

final readonly class UnavailablePeriodWriter implements UnavailablePeriodWriterInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function add(
        string $sourceId,
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void {
        $roomRow = $this->connection->fetchAssociative(
            'SELECT room_type_id, hotel_id FROM room_index WHERE room_id = :roomId',
            ['roomId' => $roomId],
        );

        if (false === $roomRow) {
            return;
        }

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO unavailable_periods (id, room_id, room_type_id, hotel_id, period, source_id)
            VALUES (:id, :roomId, :roomTypeId, :hotelId, daterange(:checkIn, :checkOut), :sourceId)
            ON CONFLICT (id) DO NOTHING
            SQL,
            [
                'id' => $sourceId,
                'roomId' => $roomId,
                'roomTypeId' => $roomRow['room_type_id'],
                'hotelId' => $roomRow['hotel_id'],
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
                'sourceId' => $sourceId,
            ],
        );
    }

    public function removeByPeriod(
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void {
        $this->connection->executeStatement(
            <<<'SQL'
            DELETE FROM unavailable_periods
            WHERE room_id = :roomId
              AND period = daterange(:checkIn, :checkOut)
            SQL,
            ['roomId' => $roomId, 'checkIn' => $checkIn->format('Y-m-d'), 'checkOut' => $checkOut->format('Y-m-d')],
        );
    }

    public function removeBySource(string $sourceId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM unavailable_periods WHERE source_id = :sourceId',
            ['sourceId' => $sourceId],
        );
    }
}
