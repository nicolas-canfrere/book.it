<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\BlockedPeriodCreated;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

#[AsEventListener(event: BlockedPeriodCreated::class)]
final readonly class BlockedPeriodCreatedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(BlockedPeriodCreated $event): void
    {
        $roomRow = $this->connection->fetchAssociative(
            'SELECT room_type_id, hotel_id FROM room_index WHERE room_id = :roomId',
            ['roomId' => $event->roomId],
        );

        if (false === $roomRow) {
            return;
        }

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO unavailable_periods (id, room_id, room_type_id, hotel_id, period)
            VALUES (:id, :roomId, :roomTypeId, :hotelId, daterange(:checkIn, :checkOut))
            ON CONFLICT (id) DO NOTHING
            SQL,
            [
                'id' => Uuid::v4()->toRfc4122(),
                'roomId' => $event->roomId,
                'roomTypeId' => $roomRow['room_type_id'],
                'hotelId' => $roomRow['hotel_id'],
                'checkIn' => $event->checkIn->format('Y-m-d'),
                'checkOut' => $event->checkOut->format('Y-m-d'),
            ],
        );
    }
}
