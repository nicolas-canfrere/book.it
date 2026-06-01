<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\DeleteSearchRoomType;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class DeleteSearchRoomTypeCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(DeleteSearchRoomTypeCommand $command): void
    {
        $this->writer->deleteRoomType($command->roomTypeId);
    }
}
