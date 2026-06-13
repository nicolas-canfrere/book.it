<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AvailabilityHoldId;
use App\Shared\Domain\ValueObject\BlockedPeriodId;
use App\Shared\Domain\ValueObject\BookerId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class IdValueObjectsTest extends TestCase
{
    private const string UUID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    #[Test]
    public function it_exposes_booker_id_value_and_casts_to_string(): void
    {
        $id = new BookerId(self::UUID);
        self::assertSame(self::UUID, $id->value);
        self::assertSame(self::UUID, (string) $id);
    }

    #[Test]
    public function it_exposes_availability_hold_id_value_and_casts_to_string(): void
    {
        $id = new AvailabilityHoldId(self::UUID);
        self::assertSame(self::UUID, $id->value);
        self::assertSame(self::UUID, (string) $id);
    }

    #[Test]
    public function it_exposes_blocked_period_id_value_and_casts_to_string(): void
    {
        $id = new BlockedPeriodId(self::UUID);
        self::assertSame(self::UUID, $id->value);
        self::assertSame(self::UUID, (string) $id);
    }
}
