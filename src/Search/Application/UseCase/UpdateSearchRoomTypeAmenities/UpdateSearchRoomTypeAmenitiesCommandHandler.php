<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class UpdateSearchRoomTypeAmenitiesCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(UpdateSearchRoomTypeAmenitiesCommand $command): void
    {
        $this->writer->updateRoomAmenities($command->roomTypeId, $command->amenities);
    }
}
