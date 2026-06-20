<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Domain\Exception\HotelAlreadyExistsException;
use App\Hotel\Domain\Exception\InvalidGeoPlaceException;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\GeoPlaceCheckerInterface;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\HotelRegistered;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class RegisterHotelCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
        private EventDispatcherInterface $eventDispatcher,
        private GeoPlaceCheckerInterface $geoPlaceChecker,
    ) {
    }

    public function __invoke(RegisterHotelCommand $command): void
    {
        if ($this->hotelRepository->existsByNameAndAddress($command->name, $command->address)) {
            throw new HotelAlreadyExistsException($command->name, $command->address->city);
        }

        $geoPlaceId = $command->address->geoPlaceId;
        if (null !== $geoPlaceId && !$this->geoPlaceChecker->exists($geoPlaceId)) {
            throw new InvalidGeoPlaceException($geoPlaceId->value);
        }

        $hotel = new Hotel($command->id, $command->name, $command->address, $command->createdAt, $command->starRating);

        $this->hotelRepository->add($hotel);

        $this->eventDispatcher->dispatch(new HotelRegistered(
            hotelId: $hotel->id->value,
            name: $hotel->name,
            city: $hotel->address->city,
            country: $hotel->address->country,
            starRating: $hotel->starRating?->stars,
            createdAt: $hotel->createdAt,
        ));
    }
}
