<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;

final class FakeCancellationPolicyFetcher implements CancellationPolicyFetcherInterface
{
    private CancellationTerms $terms;

    public function __construct()
    {
        $this->terms = CancellationTerms::alwaysRefundable();
    }

    public function setTerms(CancellationTerms $terms): void
    {
        $this->terms = $terms;
    }

    public function fetch(string $roomId): CancellationTerms
    {
        return $this->terms;
    }
}
