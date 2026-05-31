<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Reservation\Infrastructure\Service\PricingCancellationPolicyFetcher;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PricingCancellationPolicyFetcherTest extends TestCase
{
    #[Test]
    public function itReturnsCancellationTermsWithThresholdWhenPolicyExists(): void
    {
        $queryBus = $this->createStub(SyncQueryBusInterface::class);
        $queryBus->method('ask')->willReturn(
            new CancellationPolicy('room-id', 7, new \DateTimeImmutable()),
        );

        $fetcher = new PricingCancellationPolicyFetcher($queryBus);
        $terms = $fetcher->fetch('room-id');

        self::assertSame(7, $terms->daysThreshold);
    }

    #[Test]
    public function itReturnsAlwaysRefundableWhenNoPolicyExists(): void
    {
        $queryBus = $this->createStub(SyncQueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(
            new CancellationPolicyNotFoundException('room-id'),
        );

        $fetcher = new PricingCancellationPolicyFetcher($queryBus);
        $terms = $fetcher->fetch('room-id');

        self::assertNull($terms->daysThreshold);
    }
}
