<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RegisterSearchRoomType;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class RegisterSearchRoomTypeCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(RegisterSearchRoomTypeCommand $command): void
    {
        $this->writer->upsertRoomType(
            $command->roomTypeId,
            $command->hotelId,
            $command->name,
            $command->guestCapacity,
            $command->bedComposition,
        );
    }
}
