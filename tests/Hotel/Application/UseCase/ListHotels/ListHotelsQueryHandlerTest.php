<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\ListHotels;

use App\Hotel\Application\UseCase\ListHotels\ListHotelsQuery;
use App\Hotel\Application\UseCase\ListHotels\ListHotelsQueryHandler;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Shared\Domain\ValueObject\HotelId;
use App\Tests\Hotel\Infrastructure\Persistence\InMemory\InMemoryHotelRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ListHotelsQueryHandlerTest extends TestCase
{
    private InMemoryHotelRepository $repository;
    private ListHotelsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryHotelRepository();
        $this->handler = new ListHotelsQueryHandler($this->repository);
    }

    #[Test]
    public function itReturnsEmptyPageWhenNoHotelsExist(): void
    {
        $result = ($this->handler)(new ListHotelsQuery());

        self::assertCount(0, $result->hotels);
        self::assertSame(0, $result->total);
    }

    #[Test]
    public function itReturnsAllHotelsSortedByNameAscending(): void
    {
        $this->repository->add($this->makeHotel('1', 'Zara Hotel', 'Paris', 'FR'));
        $this->repository->add($this->makeHotel('2', 'Alpha Hotel', 'Paris', 'FR'));

        $result = ($this->handler)(new ListHotelsQuery());

        self::assertCount(2, $result->hotels);
        self::assertSame(2, $result->total);
        self::assertSame('Alpha Hotel', $result->hotels[0]->name);
        self::assertSame('Zara Hotel', $result->hotels[1]->name);
    }

    #[Test]
    public function itFiltersByCity(): void
    {
        $this->repository->add($this->makeHotel('1', 'Hotel Paris', 'Paris', 'FR'));
        $this->repository->add($this->makeHotel('2', 'Hotel Lyon', 'Lyon', 'FR'));

        $result = ($this->handler)(new ListHotelsQuery(city: 'Paris'));

        self::assertCount(1, $result->hotels);
        self::assertSame('Hotel Paris', $result->hotels[0]->name);
    }

    #[Test]
    public function itFiltersByCountry(): void
    {
        $this->repository->add($this->makeHotel('1', 'Hotel FR', 'Paris', 'FR'));
        $this->repository->add($this->makeHotel('2', 'Hotel DE', 'Berlin', 'DE'));

        $result = ($this->handler)(new ListHotelsQuery(country: 'DE'));

        self::assertCount(1, $result->hotels);
        self::assertSame('Hotel DE', $result->hotels[0]->name);
    }

    #[Test]
    public function itFiltersByCityAndCountrySimultaneously(): void
    {
        $this->repository->add($this->makeHotel('1', 'Hotel Paris FR', 'Paris', 'FR'));
        $this->repository->add($this->makeHotel('2', 'Hotel Paris DE', 'Paris', 'DE'));
        $this->repository->add($this->makeHotel('3', 'Hotel Lyon FR', 'Lyon', 'FR'));

        $result = ($this->handler)(new ListHotelsQuery(city: 'Paris', country: 'FR'));

        self::assertCount(1, $result->hotels);
        self::assertSame('Hotel Paris FR', $result->hotels[0]->name);
    }

    #[Test]
    public function itPaginatesResults(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->repository->add($this->makeHotel((string) $i, "Hotel {$i}", 'Paris', 'FR'));
        }

        $result = ($this->handler)(new ListHotelsQuery(page: 2, limit: 2));

        self::assertCount(2, $result->hotels);
        self::assertSame(5, $result->total);
    }

    #[Test]
    public function itReturnsCorrectTotalWhenPageExceedsResults(): void
    {
        $this->repository->add($this->makeHotel('1', 'Only Hotel', 'Paris', 'FR'));

        $result = ($this->handler)(new ListHotelsQuery(page: 99, limit: 20));

        self::assertCount(0, $result->hotels);
        self::assertSame(1, $result->total);
    }

    private function makeHotel(string $id, string $name, string $city, string $country): Hotel
    {
        return new Hotel(
            new HotelId($id),
            $name,
            new Address('1 rue Test', '75000', $city, $country),
            new \DateTimeImmutable('2024-01-01'),
        );
    }
}
