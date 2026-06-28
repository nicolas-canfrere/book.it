<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Application\UseCase\OnboardOrganization;

use App\Onboarding\Application\Port\OrganizationRegistrarInterface;
use App\Onboarding\Application\Port\OwnerOperatorRegistrarInterface;
use App\Onboarding\Application\UseCase\OnboardOrganization\OnboardOrganizationCommand;
use App\Onboarding\Application\UseCase\OnboardOrganization\OnboardOrganizationHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[Group('unit')]
final class OnboardOrganizationHandlerTest extends TestCase
{
    private OrganizationRegistrarInterface&MockObject $organizationRegistrar;
    private OwnerOperatorRegistrarInterface&MockObject $ownerRegistrar;
    private OnboardOrganizationHandler $handler;

    protected function setUp(): void
    {
        $this->organizationRegistrar = $this->createMock(OrganizationRegistrarInterface::class);
        $this->ownerRegistrar = $this->createMock(OwnerOperatorRegistrarInterface::class);
        $this->handler = new OnboardOrganizationHandler(
            $this->organizationRegistrar,
            $this->ownerRegistrar,
            new NullLogger(),
        );
    }

    #[Test]
    public function itRegistersOrganizationThenOwner(): void
    {
        $at = new \DateTimeImmutable('2026-06-28T10:00:00Z');

        $this->organizationRegistrar->expects(self::once())
            ->method('register')
            ->with('org-uuid', 'Hôtel ABC', 'owner@hotel.com', $at);

        $this->ownerRegistrar->expects(self::once())
            ->method('registerOwner')
            ->with(
                'op-uuid',
                'Alice',
                'Martin',
                'owner@hotel.com',
                '+33612345678',
                'Passw0rd!',
                'org-uuid',
                $at,
            );

        ($this->handler)(new OnboardOrganizationCommand(
            organizationId: 'org-uuid',
            operatorId: 'op-uuid',
            organizationName: 'Hôtel ABC',
            contactEmail: 'owner@hotel.com',
            ownerFirstName: 'Alice',
            ownerLastName: 'Martin',
            ownerPhone: '+33612345678',
            password: 'Passw0rd!',
            registeredAt: $at,
        ));
    }

    #[Test]
    public function itPropagatesExceptionFromOrganizationRegistrar(): void
    {
        $this->organizationRegistrar->method('register')
            ->willThrowException(new \RuntimeException('org conflict'));

        $this->ownerRegistrar->expects(self::never())->method('registerOwner');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('org conflict');

        ($this->handler)(new OnboardOrganizationCommand(
            organizationId: 'org-uuid',
            operatorId: 'op-uuid',
            organizationName: 'Hôtel ABC',
            contactEmail: 'owner@hotel.com',
            ownerFirstName: 'Alice',
            ownerLastName: 'Martin',
            ownerPhone: '+33612345678',
            password: 'Passw0rd!',
            registeredAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itCompensatesOrganizationWhenOwnerRegistrationFails(): void
    {
        $at = new \DateTimeImmutable('2026-06-28T10:00:00Z');

        $this->organizationRegistrar->expects(self::once())
            ->method('register')
            ->with('org-uuid', 'Hôtel ABC', 'owner@hotel.com', $at);

        $this->ownerRegistrar->expects(self::once())
            ->method('registerOwner')
            ->willThrowException(new \RuntimeException('Keycloak unavailable'));

        $this->organizationRegistrar->expects(self::once())
            ->method('removeOrganization')
            ->with('org-uuid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Keycloak unavailable');

        ($this->handler)(new OnboardOrganizationCommand(
            organizationId: 'org-uuid',
            operatorId: 'op-uuid',
            organizationName: 'Hôtel ABC',
            contactEmail: 'owner@hotel.com',
            ownerFirstName: 'Alice',
            ownerLastName: 'Martin',
            ownerPhone: '+33612345678',
            password: 'Passw0rd!',
            registeredAt: $at,
        ));
    }
}
