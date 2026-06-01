<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource;

use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class RemoveSearchUnavailablePeriodBySourceCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private UnavailablePeriodWriterInterface $writer)
    {
    }

    public function __invoke(RemoveSearchUnavailablePeriodBySourceCommand $command): void
    {
        $this->writer->removeBySource($command->sourceId);
    }
}
