<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Infrastructure\Persistence\Doctrine\HotelRepository;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class HotelRepositoryAmenitiesTest extends KernelTestCase
{
    // Fixed UUIDs for deterministic, isolated test rows
    private const ID_1 = 'a1000000-0000-4000-8000-000000000001';
    private const ID_2 = 'a1000000-0000-4000-8000-000000000002';
    private const ID_3 = 'a1000000-0000-4000-8000-000000000003';
    private const ID_4 = 'a1000000-0000-4000-8000-000000000004';
    private const ORG_ID = '00000000-0000-0000-0000-000000000001';

    private HotelRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->repository = $container->get(HotelRepository::class);
        $tenantContext = $container->get(TenantContext::class);
        $tenantContext->set(new OrganizationId(self::ORG_ID));
    }

    #[Test]
    public function itSaveAndReloadAmenities(): void
    {
        $hotel = new Hotel(
            new HotelId(self::ID_1),
            'Hotel Amenity Test',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
            new OrganizationId(self::ORG_ID),
        );
        $this->repository->add($hotel);

        $withAmenities = $hotel->withAmenities([HotelAmenity::Pool, HotelAmenity::Gym]);
        $this->repository->save($withAmenities);

        $reloaded = $this->repository->get(new HotelId(self::ID_1));
        self::assertNotNull($reloaded);
        self::assertSame([HotelAmenity::Pool, HotelAmenity::Gym], $reloaded->amenities);
    }

    #[Test]
    public function itSaveEmptyAmenities(): void
    {
        $hotel = new Hotel(
            new HotelId(self::ID_2),
            'Hotel Empty Amenities',
            new Address('2 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
            organizationId: new OrganizationId(self::ORG_ID),
            amenities: [HotelAmenity::Pool],
        );
        $this->repository->add($hotel);
        $this->repository->save($hotel->withAmenities([]));

        $reloaded = $this->repository->get(new HotelId(self::ID_2));
        self::assertNotNull($reloaded);
        self::assertSame([], $reloaded->amenities);
    }

    #[Test]
    public function itListFiltersByAmenitiesAndSemantics(): void
    {
        $hotelA = new Hotel(
            new HotelId(self::ID_3),
            'Hotel Pool Gym',
            new Address('3 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
            new OrganizationId(self::ORG_ID),
        );
        $this->repository->add($hotelA);
        $this->repository->save($hotelA->withAmenities([HotelAmenity::Pool, HotelAmenity::Gym]));

        $hotelB = new Hotel(
            new HotelId(self::ID_4),
            'Hotel Pool Only',
            new Address('4 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
            new OrganizationId(self::ORG_ID),
        );
        $this->repository->add($hotelB);
        $this->repository->save($hotelB->withAmenities([HotelAmenity::Pool]));

        // Filter pool only — both match
        $pagePool = $this->repository->list(1, 100, null, null, null, [HotelAmenity::Pool]);
        $ids = array_map(static fn(Hotel $h) => $h->id->value, $pagePool->hotels);
        self::assertContains(self::ID_3, $ids);
        self::assertContains(self::ID_4, $ids);

        // Filter pool+gym — only hotelA matches (AND semantics)
        $pageBoth = $this->repository->list(1, 100, null, null, null, [HotelAmenity::Pool, HotelAmenity::Gym]);
        $ids = array_map(static fn(Hotel $h) => $h->id->value, $pageBoth->hotels);
        self::assertContains(self::ID_3, $ids);
        self::assertNotContains(self::ID_4, $ids);
    }
}
