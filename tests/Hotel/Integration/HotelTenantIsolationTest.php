<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Integration;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelPublicReaderInterface;
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
    // Default Organization created by migration — already committed, visible to all connections
    private const DEFAULT_ORG_ID = '00000000-0000-0000-0000-000000000001';
    // A second org that exists only in TenantContext (no hotels) — used to verify scope rejects cross-tenant reads
    private const OTHER_ORG_ID = 'ffffffff-0000-0000-0000-000000000099';

    private HotelRepository $hotelRepository;
    private HotelPublicReaderInterface $publicReader;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->hotelRepository = $container->get(HotelRepository::class);
        $this->publicReader = $container->get(HotelPublicReaderInterface::class);
        $this->tenantContext = $container->get(TenantContext::class);
    }

    #[Test]
    public function itScopedRepositoryOnlyReturnsOwnTenantHotels(): void
    {
        $defaultOrg = new OrganizationId(self::DEFAULT_ORG_ID);
        $otherOrg = new OrganizationId(self::OTHER_ORG_ID);

        // Insert a hotel belonging to defaultOrg
        $this->tenantContext->set($defaultOrg);
        $this->hotelRepository->add($this->aHotel('11111111-0000-0000-0000-000000000001', 'Hotel Default Org', $defaultOrg));

        // As defaultOrg: can see its own hotel
        $found = $this->hotelRepository->get(new HotelId('11111111-0000-0000-0000-000000000001'));
        self::assertNotNull($found);
        self::assertTrue($defaultOrg->equals($found->organizationId));

        // Switching to otherOrg: cannot see defaultOrg's hotel
        $this->tenantContext->set($otherOrg);
        $notFound = $this->hotelRepository->get(new HotelId('11111111-0000-0000-0000-000000000001'));
        self::assertNull($notFound);
    }

    #[Test]
    public function itPublicReaderReturnsAnyHotelRegardlessOfTenant(): void
    {
        $defaultOrg = new OrganizationId(self::DEFAULT_ORG_ID);
        $otherOrg = new OrganizationId(self::OTHER_ORG_ID);

        // Insert hotels as defaultOrg
        $this->tenantContext->set($defaultOrg);
        $this->hotelRepository->add($this->aHotel('33333333-0000-0000-0000-000000000003', 'Hotel Public 1', $defaultOrg));
        $this->hotelRepository->add($this->aHotel('44444444-0000-0000-0000-000000000004', 'Hotel Public 2', $defaultOrg));

        // Public reader returns hotels regardless of which tenant is currently set
        $this->tenantContext->set($otherOrg);
        $hotel1 = $this->publicReader->get(new HotelId('33333333-0000-0000-0000-000000000003'));
        $hotel2 = $this->publicReader->get(new HotelId('44444444-0000-0000-0000-000000000004'));

        self::assertNotNull($hotel1);
        self::assertNotNull($hotel2);
        self::assertTrue($defaultOrg->equals($hotel1->organizationId));
        self::assertTrue($defaultOrg->equals($hotel2->organizationId));
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
