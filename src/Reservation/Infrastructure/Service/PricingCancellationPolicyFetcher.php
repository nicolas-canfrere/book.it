<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Pricing\Application\UseCase\GetCancellationPolicy\GetCancellationPolicyQuery;
use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class PricingCancellationPolicyFetcher implements CancellationPolicyFetcherInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function fetch(string $roomId): CancellationTerms
    {
        try {
            /** @var CancellationPolicy $policy */
            $policy = $this->queryBus->ask(new GetCancellationPolicyQuery($roomId));

            return CancellationTerms::withThreshold($policy->daysThreshold);
        } catch (CancellationPolicyNotFoundException) {
            return CancellationTerms::alwaysRefundable();
        }
    }
}
