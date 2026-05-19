<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\SetCancellationPolicy;

use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class SetCancellationPolicyCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private CancellationPolicyRepositoryInterface $cancellationPolicyRepository,
        private RoomExistsInterface $roomExists,
    ) {
    }

    public function __invoke(SetCancellationPolicyCommand $command): void
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        $this->cancellationPolicyRepository->save(new CancellationPolicy(
            roomId: $command->roomId,
            daysThreshold: $command->daysThreshold,
            updatedAt: new \DateTimeImmutable(),
        ));
    }
}
