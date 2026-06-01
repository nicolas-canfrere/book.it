<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\AddSearchUnavailablePeriod;

use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class AddSearchUnavailablePeriodCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private UnavailablePeriodWriterInterface $writer)
    {
    }

    public function __invoke(AddSearchUnavailablePeriodCommand $command): void
    {
        $this->writer->add($command->sourceId, $command->roomId, $command->checkIn, $command->checkOut);
    }
}
