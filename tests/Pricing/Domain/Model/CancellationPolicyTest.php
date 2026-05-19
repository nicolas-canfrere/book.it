<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Domain\Model;

use App\Pricing\Domain\Model\CancellationPolicy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CancellationPolicyTest extends TestCase
{
    public function test_constructs_with_valid_data(): void
    {
        $policy = new CancellationPolicy(
            roomId: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            daysThreshold: 14,
            updatedAt: new \DateTimeImmutable('2026-05-19 00:00:00'),
        );

        self::assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $policy->roomId);
        self::assertSame(14, $policy->daysThreshold);
        self::assertSame('2026-05-19T00:00:00+00:00', $policy->updatedAt->format(\DateTimeInterface::ATOM));
    }

    public function test_throws_on_zero_threshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Days threshold must be greater than zero.');

        new CancellationPolicy(
            roomId: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            daysThreshold: 0,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function test_throws_on_negative_threshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Days threshold must be greater than zero.');

        new CancellationPolicy(
            roomId: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            daysThreshold: -5,
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
