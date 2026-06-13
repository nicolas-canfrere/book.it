<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ClassifyHotel;

use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\StarRatingClassified;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class ClassifyHotelCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ClassifyHotelCommand $command): void
    {
        $hotel = $this->hotelRepository->get($command->hotelId);

        if (null === $hotel) {
            throw new HotelNotFoundException($command->hotelId->value);
        }

        $this->hotelRepository->save($hotel->withStarRating($command->starRating));

        $this->eventDispatcher->dispatch(new StarRatingClassified(
            hotelId: $command->hotelId->value,
            starRating: $command->starRating?->stars,
        ));
    }
}
