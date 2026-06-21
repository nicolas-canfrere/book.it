<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Service;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Pricing\Application\Contract\BaseRateView;
use App\Room\Infrastructure\Service\BaseRateFinder;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BaseRateFinderTest extends TestCase
{
    private BaseRateFinderInterface&Stub $pricingFinder;
    private BaseRateFinder $finder;

    protected function setUp(): void
    {
        $this->pricingFinder = $this->createStub(BaseRateFinderInterface::class);
        $this->finder = new BaseRateFinder($this->pricingFinder);
    }

    #[Test]
    public function itReturnsAmountCentsWhenBaseRateExists(): void
    {
        $this->pricingFinder->method('find')->willReturn(new BaseRateView(12000));

        self::assertSame(12000, $this->finder->find(new RoomId('room-1')));
    }

    #[Test]
    public function itReturnsNullWhenNoBaseRate(): void
    {
        $this->pricingFinder->method('find')->willReturn(null);

        self::assertNull($this->finder->find(new RoomId('room-1')));
    }
}
