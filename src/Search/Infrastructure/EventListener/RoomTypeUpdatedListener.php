<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\RoomTypeUpdated;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeUpdated::class)]
final readonly class RoomTypeUpdatedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(RoomTypeUpdated $event): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE hotel_room_types
            SET room_type_name  = :name,
                guest_capacity  = :guestCapacity,
                bed_composition = :bedComposition
            WHERE room_type_id = :roomTypeId
            SQL,
            [
                'name' => $event->name,
                'guestCapacity' => $event->guestCapacity,
                'bedComposition' => json_encode($event->bedComposition, \JSON_THROW_ON_ERROR),
                'roomTypeId' => $event->roomTypeId,
            ],
        );
    }
}
