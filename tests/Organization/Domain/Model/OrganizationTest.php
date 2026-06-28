<?php

declare(strict_types=1);

namespace App\Tests\Organization\Domain\Model;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationStatus;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\Event\OrganizationRegistered;
use App\Shared\Domain\Event\OrganizationSuspended;
use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OrganizationTest extends TestCase
{
    #[Test]
    public function itRegistersAnOrganizationWithPendingStatus(): void
    {
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $at = new \DateTimeImmutable('2026-06-27T10:00:00Z');

        $org = Organization::register($id, new OrganizationName('Hotel ABC'), new OrganizationEmail('abc@hotel.fr'), $at);

        self::assertSame(OrganizationStatus::Pending, $org->status);
        self::assertTrue($id->equals($org->id));

        $events = $org->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrganizationRegistered::class, $events[0]);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $events[0]->organizationId);
        self::assertSame('abc@hotel.fr', $events[0]->contactEmail);
    }

    #[Test]
    public function itActivatesAPendingOrganization(): void
    {
        $org = $this->aPendingOrganization();
        $org->pullEvents(); // vider les events du register

        $org->activate();

        self::assertSame(OrganizationStatus::Active, $org->status);
        self::assertEmpty($org->pullEvents());
    }

    #[Test]
    public function itSuspendsAnActiveOrganization(): void
    {
        $org = $this->anActiveOrganization();
        $org->pullEvents();

        $at = new \DateTimeImmutable('2026-06-28T00:00:00Z');
        $org->suspend($at);

        self::assertSame(OrganizationStatus::Suspended, $org->status);
        $events = $org->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrganizationSuspended::class, $events[0]);
    }

    private function aPendingOrganization(): Organization
    {
        return Organization::register(
            new OrganizationId('550e8400-e29b-41d4-a716-446655440000'),
            new OrganizationName('Test Hotel'),
            new OrganizationEmail('test@hotel.fr'),
            new \DateTimeImmutable('2026-06-27T10:00:00Z'),
        );
    }

    private function anActiveOrganization(): Organization
    {
        $org = $this->aPendingOrganization();
        $org->activate();

        return $org;
    }
}
