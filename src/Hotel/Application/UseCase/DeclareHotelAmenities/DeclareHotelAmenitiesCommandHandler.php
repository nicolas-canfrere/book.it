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
            throw new HotelNotFoundException($command->hotelId->value);
        }

        $this->hotelRepository->save($hotel->withAmenities($command->amenities));

        $this->eventDispatcher->dispatch(new HotelAmenityDeclared(
            hotelId: $command->hotelId->value,
            amenities: array_map(static fn(HotelAmenity $a) => $a->value, $command->amenities),
        ));
    }
}
