<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ClassifyHotel;

use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class ClassifyHotelCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
    ) {
    }

    public function __invoke(ClassifyHotelCommand $command): void
    {
        $hotel = $this->hotelRepository->get($command->hotelId);

        if (null === $hotel) {
            throw new HotelNotFoundException($command->hotelId);
        }

        $starRating = null !== $command->stars
            ? new StarRating($command->stars, $command->superior)
            : null;

        $this->hotelRepository->save($hotel->withStarRating($starRating));
    }
}
