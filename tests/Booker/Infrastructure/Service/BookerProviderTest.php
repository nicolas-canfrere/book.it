<?php

declare(strict_types=1);

namespace App\Tests\Booker\Infrastructure\Service;

use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Booker\Domain\Model\Booker;
use App\Booker\Infrastructure\Service\BookerProvider;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BookerProviderTest extends TestCase
{
    #[Test]
    public function itReturnsTrueWhenBookerExists(): void
    {
        $booker = new Booker(
            id: '550e8400-e29b-41d4-a716-446655440001',
            firstName: 'Alice',
            lastName: 'Martin',
            email: 'alice@example.com',
            phone: '+33600000000',
            dateOfBirth: new \DateTimeImmutable('1990-01-01'),
            registeredAt: new \DateTimeImmutable('2026-01-01'),
        );

        $queryBus = $this->createMock(SyncQueryBusInterface::class);
        $queryBus
            ->expects($this->once())
            ->method('ask')
            ->with($this->equalTo(new GetBookerQuery('550e8400-e29b-41d4-a716-446655440001')))
            ->willReturn($booker);

        $provider = new BookerProvider($queryBus);

        self::assertTrue($provider->exists('550e8400-e29b-41d4-a716-446655440001'));
    }

    #[Test]
    public function itReturnsFalseWhenBookerDoesNotExist(): void
    {
        $queryBus = $this->createMock(SyncQueryBusInterface::class);
        $queryBus
            ->expects($this->once())
            ->method('ask')
            ->with($this->equalTo(new GetBookerQuery('550e8400-e29b-41d4-a716-446655440002')))
            ->willReturn(null);

        $provider = new BookerProvider($queryBus);

        self::assertFalse($provider->exists('550e8400-e29b-41d4-a716-446655440002'));
    }
}
