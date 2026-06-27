<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Infrastructure\Persistence\Doctrine\HotelRepository;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class HotelRepositoryGeoPlaceTest extends KernelTestCase
{
    private const ID_WITH_GEO_PLACE = 'a2000000-0000-4000-8000-000000000001';
    private const ID_WITHOUT_GEO_PLACE = 'a2000000-0000-4000-8000-000000000002';
    private const ORG_ID = '00000000-0000-0000-0000-000000000001';

    private HotelRepository $repository;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->repository = $container->get(HotelRepository::class);
        $this->tenantContext = $container->get(TenantContext::class);
        $this->tenantContext->set(new OrganizationId(self::ORG_ID));
    }

    #[Test]
    public function itSaveAndReloadAGeoPlaceId(): void
    {
        $hotel = new Hotel(
            new HotelId(self::ID_WITH_GEO_PLACE),
            'Hotel With Geo Place',
            new Address('1 rue Test', '75001', 'Paris', 'FR', new GeoPlaceId('2988507')),
            new \DateTimeImmutable('2025-01-01'),
            new OrganizationId(self::ORG_ID),
        );
        $this->repository->add($hotel);

        $reloaded = $this->repository->get(new HotelId(self::ID_WITH_GEO_PLACE));
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->address->geoPlaceId);
        self::assertSame('2988507', $reloaded->address->geoPlaceId->value);
    }

    #[Test]
    public function itSaveAndReloadANullGeoPlaceId(): void
    {
        $hotel = new Hotel(
            new HotelId(self::ID_WITHOUT_GEO_PLACE),
            'Hotel Without Geo Place',
            new Address('2 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
            new OrganizationId(self::ORG_ID),
        );
        $this->repository->add($hotel);

        $reloaded = $this->repository->get(new HotelId(self::ID_WITHOUT_GEO_PLACE));
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->address->geoPlaceId);
    }
}
