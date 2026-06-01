<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\HotelAmenityDeclared;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: HotelAmenityDeclared::class)]
final readonly class HotelAmenityDeclaredListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(HotelAmenityDeclared $event): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET hotel_amenities = :amenities WHERE hotel_id = :hotelId',
            [
                'amenities' => json_encode($event->amenities, \JSON_THROW_ON_ERROR),
                'hotelId' => $event->hotelId,
            ],
        );
    }
}
