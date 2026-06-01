<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchHotelStarRating;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class UpdateSearchHotelStarRatingCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(UpdateSearchHotelStarRatingCommand $command): void
    {
        $this->writer->updateStarRating($command->hotelId, $command->starRating);
    }
}
