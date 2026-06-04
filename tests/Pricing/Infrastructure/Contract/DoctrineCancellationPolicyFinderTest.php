<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\CancellationPolicyFinderInterface;
use App\Pricing\Application\Contract\CancellationPolicyView;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Pricing\Infrastructure\Contract\DoctrineCancellationPolicyFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineCancellationPolicyFinderTest extends TestCase
{
    private CancellationPolicyRepositoryInterface&Stub $repository;
    private CancellationPolicyFinderInterface $finder;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(CancellationPolicyRepositoryInterface::class);
        $this->finder = new DoctrineCancellationPolicyFinder($this->repository);
    }

    #[Test]
    public function itReturnsViewWhenPolicyExists(): void
    {
        $policy = new CancellationPolicy('room-1', 7, new \DateTimeImmutable());
        $this->repository->method('findByRoomId')->willReturn($policy);

        $view = $this->finder->find('room-1');

        self::assertInstanceOf(CancellationPolicyView::class, $view);
        self::assertSame(7, $view->daysThreshold);
    }

    #[Test]
    public function itReturnsNullWhenNoPolicy(): void
    {
        $this->repository->method('findByRoomId')->willReturn(null);
        self::assertNull($this->finder->find('room-1'));
    }
}
