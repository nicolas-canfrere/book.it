<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Pricing\Application\Contract\BaseRateView;
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
    public function itReturnsViewWhenBaseRateExists(): void
    {
        $baseRate = new BaseRate(new RoomId('room-1'), 12000, new \DateTimeImmutable());
        $this->repository->method('findByRoomId')->willReturn($baseRate);

        $view = $this->finder->find(new RoomId('room-1'));

        self::assertInstanceOf(BaseRateView::class, $view);
        self::assertSame(12000, $view->amountCents);
    }

    #[Test]
    public function itReturnsNullWhenNoBaseRate(): void
    {
        $this->repository->method('findByRoomId')->willReturn(null);

        self::assertNull($this->finder->find(new RoomId('room-1')));
    }
}
