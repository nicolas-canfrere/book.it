<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Unit\Application\UseCase\ClassifyHotel;

use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommand;
use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommandHandler;
use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Tests\Hotel\Infrastructure\Persistence\InMemory\InMemoryHotelRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class ClassifyHotelCommandHandlerTest extends TestCase
{
    private InMemoryHotelRepository $repository;
    private EventDispatcherInterface $dispatcher;
    private ClassifyHotelCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryHotelRepository();
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->handler = new ClassifyHotelCommandHandler($this->repository, $this->dispatcher);
    }

    public function test_it_sets_a_star_rating(): void
    {
        $this->repository->add($this->aHotel('hotel-1'));

        ($this->handler)(new ClassifyHotelCommand('hotel-1', 4, false));

        $saved = $this->repository->get('hotel-1');
        self::assertNotNull($saved);
        self::assertNotNull($saved->starRating);
        self::assertSame(4, $saved->starRating->stars);
        self::assertFalse($saved->starRating->superior);
    }

    public function test_it_sets_a_superior_star_rating(): void
    {
        $this->repository->add($this->aHotel('hotel-1'));

        ($this->handler)(new ClassifyHotelCommand('hotel-1', 5, true));

        $saved = $this->repository->get('hotel-1');
        self::assertNotNull($saved);
        self::assertNotNull($saved->starRating);
        self::assertSame(5, $saved->starRating->stars);
        self::assertTrue($saved->starRating->superior);
    }

    public function test_it_removes_a_star_rating_when_stars_is_null(): void
    {
        $hotel = $this->aHotel('hotel-1')->withStarRating(new StarRating(3, false));
        $this->repository->add($hotel);

        ($this->handler)(new ClassifyHotelCommand('hotel-1', null, false));

        $saved = $this->repository->get('hotel-1');
        self::assertNotNull($saved);
        self::assertNull($saved->starRating);
    }

    public function test_it_throws_when_hotel_not_found(): void
    {
        $this->expectException(HotelNotFoundException::class);

        ($this->handler)(new ClassifyHotelCommand('unknown-id', 3, false));
    }

    private function aHotel(string $id = 'e4e1c9b0-1234-4a2b-9c3f-aabbccddeeff'): Hotel
    {
        return new Hotel(
            $id,
            'Hotel Test',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
        );
    }
}
