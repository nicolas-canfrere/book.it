<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\CancellationNotAllowedException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationCancelByBookerTest extends TestCase
{
    #[Test]
    public function itThrowsCancellationNotAllowedOnCheckInDate(): void
    {
        $this->expectException(CancellationNotAllowedException::class);
        $this->expectExceptionMessage('2026-06-15');

        throw CancellationNotAllowedException::afterCheckIn(
            new \DateTimeImmutable('2026-06-15'),
            new \DateTimeImmutable('2026-06-15'),
        );
    }
}
