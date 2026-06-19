<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Model\ReservationStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationStatusTest extends TestCase
{
    #[Test]
    public function itListsAllValues(): void
    {
        self::assertSame(
            ['pending', 'confirmed', 'cancelled', 'expired', 'checked_in', 'checked_out'],
            ReservationStatus::values(),
        );
    }
}
