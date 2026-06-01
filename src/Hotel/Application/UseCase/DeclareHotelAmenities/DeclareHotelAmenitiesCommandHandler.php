<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\DeclareHotelAmenities;

use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\HotelAmenityDeclared;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DeclareHotelAmenitiesCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(DeclareHotelAmenitiesCommand $command): void
    {
        $hotel = $this->hotelRepository->get($command->hotelId);

        if (null === $hotel) {
            throw new HotelNotFoundException($command->hotelId);
        }

        $amenities = array_map(HotelAmenity::from(...), $command->amenities);

        $this->hotelRepository->save($hotel->withAmenities($amenities));

        $this->eventDispatcher->dispatch(new HotelAmenityDeclared(
            hotelId: $command->hotelId,
            amenities: $command->amenities,
        ));
    }
}
