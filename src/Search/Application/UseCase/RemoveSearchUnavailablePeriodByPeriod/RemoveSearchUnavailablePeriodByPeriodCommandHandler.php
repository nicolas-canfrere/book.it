<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod;

use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class RemoveSearchUnavailablePeriodByPeriodCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private UnavailablePeriodWriterInterface $writer)
    {
    }

    public function __invoke(RemoveSearchUnavailablePeriodByPeriodCommand $command): void
    {
        $this->writer->removeByPeriod($command->roomId, $command->checkIn, $command->checkOut);
    }
}
