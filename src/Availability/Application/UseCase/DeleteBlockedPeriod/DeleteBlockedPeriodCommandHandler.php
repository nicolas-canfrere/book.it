<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Availability\Domain\Exception\BlockedPeriodNotFoundException;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeleteBlockedPeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private BlockedPeriodRepositoryInterface $repository)
    {
    }

    public function __invoke(DeleteBlockedPeriodCommand $command): void
    {
        if (null === $this->repository->get($command->id)) {
            throw new BlockedPeriodNotFoundException($command->id);
        }

        $this->repository->remove($command->id);
    }
}
