<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Pricing\Application\Contract\CancellationPolicyFinderInterface;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class PricingCancellationPolicyFetcher implements CancellationPolicyFetcherInterface
{
    public function __construct(private CancellationPolicyFinderInterface $cancellationPolicies)
    {
    }

    public function fetch(RoomId $roomId): CancellationTerms
    {
        $view = $this->cancellationPolicies->find($roomId->value);

        if (null === $view) {
            return CancellationTerms::alwaysRefundable();
        }

        return CancellationTerms::withThreshold($view->daysThreshold);
    }
}
