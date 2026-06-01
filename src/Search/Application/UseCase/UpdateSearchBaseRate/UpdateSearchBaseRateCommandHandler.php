<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchBaseRate;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class UpdateSearchBaseRateCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(UpdateSearchBaseRateCommand $command): void
    {
        $this->writer->updateBaseRateByRoom($command->roomId, $command->amountCents);
    }
}
