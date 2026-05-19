<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetCancellationPolicy;

use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetCancellationPolicyQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private CancellationPolicyRepositoryInterface $cancellationPolicyRepository,
    ) {
    }

    public function __invoke(GetCancellationPolicyQuery $query): CancellationPolicy
    {
        $policy = $this->cancellationPolicyRepository->findByRoomId($query->roomId);

        if (null === $policy) {
            throw new CancellationPolicyNotFoundException($query->roomId);
        }

        return $policy;
    }
}
