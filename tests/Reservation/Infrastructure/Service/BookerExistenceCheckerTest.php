<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Reservation\Infrastructure\Service\BookerExistenceChecker;
use App\Shared\Domain\Port\BookerProviderInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BookerExistenceCheckerTest extends TestCase
{
    #[Test]
    public function itReturnsTrueWhenProviderConfirmsExistence(): void
    {
        $provider = $this->createMock(BookerProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('exists')
            ->with('550e8400-e29b-41d4-a716-446655440001')
            ->willReturn(true);

        $checker = new BookerExistenceChecker($provider);

        self::assertTrue($checker->exists('550e8400-e29b-41d4-a716-446655440001'));
    }

    #[Test]
    public function itReturnsFalseWhenProviderDeniesExistence(): void
    {
        $provider = $this->createMock(BookerProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('exists')
            ->with('550e8400-e29b-41d4-a716-446655440002')
            ->willReturn(false);

        $checker = new BookerExistenceChecker($provider);

        self::assertFalse($checker->exists('550e8400-e29b-41d4-a716-446655440002'));
    }
}
