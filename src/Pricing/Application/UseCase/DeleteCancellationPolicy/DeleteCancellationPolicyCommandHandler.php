<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeleteCancellationPolicy;

use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeleteCancellationPolicyCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private CancellationPolicyRepositoryInterface $cancellationPolicyRepository,
    ) {
    }

    public function __invoke(DeleteCancellationPolicyCommand $command): void
    {
        if (null === $this->cancellationPolicyRepository->findByRoomId($command->roomId)) {
            throw new CancellationPolicyNotFoundException($command->roomId);
        }

        $this->cancellationPolicyRepository->deleteByRoomId($command->roomId);
    }
}
