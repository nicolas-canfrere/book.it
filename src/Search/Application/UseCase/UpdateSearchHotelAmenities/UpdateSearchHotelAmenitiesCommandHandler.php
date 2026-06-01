<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchHotelAmenities;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class UpdateSearchHotelAmenitiesCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(UpdateSearchHotelAmenitiesCommand $command): void
    {
        $this->writer->updateHotelAmenities($command->hotelId, $command->amenities);
    }
}
