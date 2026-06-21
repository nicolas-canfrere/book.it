<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Pricing\Domain\Model\BaseRate;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Pricing\Infrastructure\Contract\DoctrineBaseRateFinder;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineBaseRateFinderTest extends TestCase
{
    private BaseRateRepositoryInterface&Stub $repository;
    private BaseRateFinderInterface $finder;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(BaseRateRepositoryInterface::class);
        $this->finder = new DoctrineBaseRateFinder($this->repository);
    }

    #[Test]
    public function itReturnsViewsKeyedByRoomIdWhenBaseRatesExist(): void
    {
        $baseRate = new BaseRate(new RoomId('room-1'), 12000, new \DateTimeImmutable());
        $this->repository->method('findByRoomIds')->willReturn(['room-1' => $baseRate]);

        $views = $this->finder->findByRoomIds([new RoomId('room-1')]);

        self::assertSame(12000, $views['room-1']->amountCents);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoBaseRatesMatch(): void
    {
        $this->repository->method('findByRoomIds')->willReturn([]);

        self::assertSame([], $this->finder->findByRoomIds([new RoomId('room-1')]));
    }
}
