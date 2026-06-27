<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Integration;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Infrastructure\Persistence\Doctrine\HotelPublicReader;
use App\Hotel\Infrastructure\Persistence\Doctrine\HotelRepository;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class HotelTenantIsolationTest extends KernelTestCase
{
    private HotelRepository $hotelRepository;
    private HotelPublicReader $publicReader;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->hotelRepository = $container->get(HotelRepository::class);
        $this->publicReader = $container->get(HotelPublicReader::class);
        $this->tenantContext = $container->get(TenantContext::class);
    }

    #[Test]
    public function itScopedRepositoryOnlyReturnsOwnTenantHotels(): void
    {
        $org1 = new OrganizationId('aaaaaaaa-0000-0000-0000-000000000001');
        $org2 = new OrganizationId('bbbbbbbb-0000-0000-0000-000000000002');

        $this->tenantContext->set($org1);
        $this->hotelRepository->add($this->aHotel('11111111-0000-0000-0000-000000000001', 'Hotel Org 1', $org1));

        $this->tenantContext->set($org2);
        $this->hotelRepository->add($this->aHotel('22222222-0000-0000-0000-000000000002', 'Hotel Org 2', $org2));

        // org1 sees its own hotel
        $this->tenantContext->set($org1);
        $found = $this->hotelRepository->get(new HotelId('11111111-0000-0000-0000-000000000001'));
        self::assertNotNull($found);
        self::assertTrue($org1->equals($found->organizationId));

        // org1 does NOT see org2's hotel
        $notFound = $this->hotelRepository->get(new HotelId('22222222-0000-0000-0000-000000000002'));
        self::assertNull($notFound);
    }

    #[Test]
    public function itPublicReaderReturnsAnyHotelRegardlessOfTenant(): void
    {
        $org1 = new OrganizationId('aaaaaaaa-0000-0000-0000-000000000001');
        $org2 = new OrganizationId('bbbbbbbb-0000-0000-0000-000000000002');

        $this->tenantContext->set($org1);
        $this->hotelRepository->add($this->aHotel('33333333-0000-0000-0000-000000000003', 'Hotel Public 1', $org1));

        $this->tenantContext->set($org2);
        $this->hotelRepository->add($this->aHotel('44444444-0000-0000-0000-000000000004', 'Hotel Public 2', $org2));

        // Public reader returns any hotel regardless of TenantContext
        $hotel1 = $this->publicReader->get(new HotelId('33333333-0000-0000-0000-000000000003'));
        $hotel2 = $this->publicReader->get(new HotelId('44444444-0000-0000-0000-000000000004'));

        self::assertNotNull($hotel1);
        self::assertNotNull($hotel2);
        self::assertTrue($org1->equals($hotel1->organizationId));
        self::assertTrue($org2->equals($hotel2->organizationId));
    }

    private function aHotel(string $id, string $name, OrganizationId $orgId): Hotel
    {
        return new Hotel(
            new HotelId($id),
            $name,
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable(),
            $orgId,
        );
    }
}
