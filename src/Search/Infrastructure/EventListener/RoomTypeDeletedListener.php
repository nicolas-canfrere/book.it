<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\RoomTypeDeleted;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeDeleted::class)]
final readonly class RoomTypeDeletedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(RoomTypeDeleted $event): void
    {
        $this->connection->executeStatement(
            'DELETE FROM hotel_room_types WHERE room_type_id = :roomTypeId',
            ['roomTypeId' => $event->roomTypeId],
        );
    }
}
