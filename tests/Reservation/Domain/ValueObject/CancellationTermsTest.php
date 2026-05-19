<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\ValueObject;

use App\Reservation\Domain\ValueObject\CancellationTerms;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CancellationTermsTest extends TestCase
{
    #[Test]
    public function alwaysRefundableHasNullThreshold(): void
    {
        $terms = CancellationTerms::alwaysRefundable();

        self::assertNull($terms->daysThreshold);
    }

    #[Test]
    public function withThresholdStoresTheDays(): void
    {
        $terms = CancellationTerms::withThreshold(7);

        self::assertSame(7, $terms->daysThreshold);
    }

    #[Test]
    public function withThresholdRejectsZeroOrNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CancellationTerms::withThreshold(0);
    }

    #[Test]
    public function alwaysRefundableIsAlwaysRefundable(): void
    {
        $terms = CancellationTerms::alwaysRefundable();

        self::assertTrue($terms->isRefundable(
            new \DateTimeImmutable('2026-06-09'),
            new \DateTimeImmutable('2026-06-10'),
        ));
    }

    #[Test]
    public function cancellationStrictlyBeforeDeadlineIsRefundable(): void
    {
        // threshold 3 days, check-in June 10 → deadline June 7
        // cancel on June 6 (strictly before June 7) → refundable
        $terms = CancellationTerms::withThreshold(3);

        self::assertTrue($terms->isRefundable(
            new \DateTimeImmutable('2026-06-06'),
            new \DateTimeImmutable('2026-06-10'),
        ));
    }

    #[Test]
    public function cancellationOnDeadlineDayIsNotRefundable(): void
    {
        // threshold 3 days, check-in June 10 → deadline June 7
        // cancel on June 7 (on the deadline) → NOT refundable
        $terms = CancellationTerms::withThreshold(3);

        self::assertFalse($terms->isRefundable(
            new \DateTimeImmutable('2026-06-07'),
            new \DateTimeImmutable('2026-06-10'),
        ));
    }

    #[Test]
    public function cancellationAfterDeadlineIsNotRefundable(): void
    {
        $terms = CancellationTerms::withThreshold(3);

        self::assertFalse($terms->isRefundable(
            new \DateTimeImmutable('2026-06-09'),
            new \DateTimeImmutable('2026-06-10'),
        ));
    }

    #[Test]
    public function timeOfDayIsIgnored(): void
    {
        // cancel at 23:59 on June 6 — still a June 6 cancellation, still refundable
        $terms = CancellationTerms::withThreshold(3);

        self::assertTrue($terms->isRefundable(
            new \DateTimeImmutable('2026-06-06T23:59:59Z'),
            new \DateTimeImmutable('2026-06-10T14:00:00Z'),
        ));
    }
}
