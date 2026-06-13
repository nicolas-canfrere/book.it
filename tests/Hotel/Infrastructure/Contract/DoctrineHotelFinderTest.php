<?php

declare(strict_types=1);

namespace Tests\Hotel\Infrastructure\Contract;

use App\Hotel\Application\Contract\HotelFinderInterface;
use App\Hotel\Application\Contract\HotelView;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Infrastructure\Contract\DoctrineHotelFinder;
use App\Shared\Domain\ValueObject\HotelId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineHotelFinderTest extends TestCase
{
    private HotelRepositoryInterface&Stub $repository;
    private HotelFinderInterface $finder;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(HotelRepositoryInterface::class);
        $this->finder = new DoctrineHotelFinder($this->repository);
    }

    #[Test]
    public function itReturnsNullWhenHotelDoesNotExist(): void
    {
        $this->repository->method('get')->willReturn(null);

        self::assertNull($this->finder->find('unknown'));
    }

    #[Test]
    public function itReturnsHotelViewWhenHotelExists(): void
    {
        $hotel = new Hotel(
            id: new HotelId('hotel-1'),
            name: 'Test Hotel',
            address: new Address('1 rue Test', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable(),
        );
        $this->repository->method('get')->willReturn($hotel);

        $view = $this->finder->find('hotel-1');

        self::assertInstanceOf(HotelView::class, $view);
        self::assertSame('hotel-1', $view->id);
    }
}
