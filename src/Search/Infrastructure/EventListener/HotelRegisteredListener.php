<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\HotelRegistered;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: HotelRegistered::class)]
final readonly class HotelRegisteredListener
{
    public function __invoke(HotelRegistered $event): void
    {
        // Hotel data is denormalized into search.hotel_room_types rows
        // when RoomTypeRegistered fires. Nothing to do here yet.
    }
}
