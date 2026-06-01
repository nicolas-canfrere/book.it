<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\UpdateSearchHotelAmenities\UpdateSearchHotelAmenitiesCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\HotelAmenityDeclared;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: HotelAmenityDeclared::class)]
final readonly class HotelAmenityDeclaredListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(HotelAmenityDeclared $event): void
    {
        $this->commandDispatcher->dispatch(new UpdateSearchHotelAmenitiesCommand(
            hotelId: $event->hotelId,
            amenities: $event->amenities,
        ));
    }
}
