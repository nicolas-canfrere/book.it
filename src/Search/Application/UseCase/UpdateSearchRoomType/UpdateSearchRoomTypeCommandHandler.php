<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchRoomType;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class UpdateSearchRoomTypeCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(UpdateSearchRoomTypeCommand $command): void
    {
        $this->writer->updateRoomType(
            $command->roomTypeId,
            $command->name,
            $command->guestCapacity,
            $command->bedComposition,
        );
    }
}
