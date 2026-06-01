<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\RoomTypeAmenityDeclared;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeAmenityDeclared::class)]
final readonly class RoomTypeAmenityDeclaredListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(RoomTypeAmenityDeclared $event): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET room_amenities = :amenities WHERE room_type_id = :roomTypeId',
            [
                'amenities' => json_encode($event->amenities, \JSON_THROW_ON_ERROR),
                'roomTypeId' => $event->roomTypeId,
            ],
        );
    }
}
