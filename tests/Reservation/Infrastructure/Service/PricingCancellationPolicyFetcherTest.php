<?php

declare(strict_types=1);

namespace Tests\Reservation\Infrastructure\Service;

use App\Pricing\Application\Contract\CancellationPolicyFinderInterface;
use App\Pricing\Application\Contract\CancellationPolicyView;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Infrastructure\Service\PricingCancellationPolicyFetcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PricingCancellationPolicyFetcherTest extends TestCase
{
    private CancellationPolicyFinderInterface&Stub $policyFinder;
    private CancellationPolicyFetcherInterface $fetcher;

    protected function setUp(): void
    {
        $this->policyFinder = $this->createStub(CancellationPolicyFinderInterface::class);
        $this->fetcher = new PricingCancellationPolicyFetcher($this->policyFinder);
    }

    #[Test]
    public function itReturnsTermsWithThresholdWhenPolicyExists(): void
    {
        $this->policyFinder->method('find')->willReturn(new CancellationPolicyView(7));

        $terms = $this->fetcher->fetch('room-1');

        self::assertSame(7, $terms->daysThreshold);
    }

    #[Test]
    public function itReturnsAlwaysRefundableWhenNoPolicy(): void
    {
        $this->policyFinder->method('find')->willReturn(null);

        $terms = $this->fetcher->fetch('room-1');

        self::assertNull($terms->daysThreshold);
    }
}
