<?php

declare(strict_types=1);

namespace App\Tests\Organization\Application\UseCase\ActivateOrganization;

use App\Organization\Application\UseCase\ActivateOrganization\ActivateOrganizationCommand;
use App\Organization\Application\UseCase\ActivateOrganization\ActivateOrganizationHandler;
use App\Organization\Domain\Exception\OrganizationNotFoundException;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationStatus;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\ValueObject\OrganizationId;
use App\Tests\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ActivateOrganizationHandlerTest extends TestCase
{
    #[Test]
    public function itActivatesAPendingOrganization(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $repo->add(Organization::register($id, new OrganizationName('Hotel X'), new OrganizationEmail('x@hotel.fr'), new \DateTimeImmutable()));

        $handler = new ActivateOrganizationHandler($repo);
        ($handler)(new ActivateOrganizationCommand($id));

        $saved = $repo->get($id);
        self::assertNotNull($saved);
        self::assertSame(OrganizationStatus::Active, $saved->status);
    }

    #[Test]
    public function itThrowsWhenOrganizationNotFound(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $handler = new ActivateOrganizationHandler($repo);

        $this->expectException(OrganizationNotFoundException::class);
        ($handler)(new ActivateOrganizationCommand(new OrganizationId('00000000-0000-0000-0000-000000000000')));
    }
}
