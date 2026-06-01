<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RegisterSearchRoom;

use App\Search\Domain\Port\RoomIndexWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class RegisterSearchRoomCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private RoomIndexWriterInterface $writer)
    {
    }

    public function __invoke(RegisterSearchRoomCommand $command): void
    {
        $this->writer->upsert($command->roomId, $command->roomTypeId, $command->hotelId);
    }
}
