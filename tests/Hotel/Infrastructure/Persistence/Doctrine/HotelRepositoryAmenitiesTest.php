<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Infrastructure\Persistence\Doctrine\HotelRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class HotelRepositoryAmenitiesTest extends KernelTestCase
{
    // Fixed UUIDs for deterministic, isolated test rows
    private const ID_1 = 'a1000000-0000-4000-8000-000000000001';
    private const ID_2 = 'a1000000-0000-4000-8000-000000000002';
    private const ID_3 = 'a1000000-0000-4000-8000-000000000003';
    private const ID_4 = 'a1000000-0000-4000-8000-000000000004';
    private HotelRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(HotelRepository::class);
    }

    public function test_save_and_reload_amenities(): void
    {
        $hotel = new Hotel(
            self::ID_1,
            'Hotel Amenity Test',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
        );
        $this->repository->add($hotel);

        $withAmenities = $hotel->withAmenities([HotelAmenity::Pool, HotelAmenity::Gym]);
        $this->repository->save($withAmenities);

        $reloaded = $this->repository->get(self::ID_1);
        self::assertNotNull($reloaded);
        self::assertSame([HotelAmenity::Pool, HotelAmenity::Gym], $reloaded->amenities);
    }

    public function test_save_empty_amenities(): void
    {
        $hotel = new Hotel(
            self::ID_2,
            'Hotel Empty Amenities',
            new Address('2 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
            amenities: [HotelAmenity::Pool],
        );
        $this->repository->add($hotel);
        $this->repository->save($hotel->withAmenities([]));

        $reloaded = $this->repository->get(self::ID_2);
        self::assertNotNull($reloaded);
        self::assertSame([], $reloaded->amenities);
    }

    public function test_list_filters_by_amenities_and_semantics(): void
    {
        $hotelA = new Hotel(
            self::ID_3,
            'Hotel Pool Gym',
            new Address('3 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
        );
        $this->repository->add($hotelA);
        $this->repository->save($hotelA->withAmenities([HotelAmenity::Pool, HotelAmenity::Gym]));

        $hotelB = new Hotel(
            self::ID_4,
            'Hotel Pool Only',
            new Address('4 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
        );
        $this->repository->add($hotelB);
        $this->repository->save($hotelB->withAmenities([HotelAmenity::Pool]));

        // Filter pool only — both match
        $pagePool = $this->repository->list(1, 100, null, null, null, [HotelAmenity::Pool]);
        $ids = array_column($pagePool->hotels, 'id');
        self::assertContains(self::ID_3, $ids);
        self::assertContains(self::ID_4, $ids);

        // Filter pool+gym — only hotelA matches (AND semantics)
        $pageBoth = $this->repository->list(1, 100, null, null, null, [HotelAmenity::Pool, HotelAmenity::Gym]);
        $ids = array_column($pageBoth->hotels, 'id');
        self::assertContains(self::ID_3, $ids);
        self::assertNotContains(self::ID_4, $ids);
    }
}
