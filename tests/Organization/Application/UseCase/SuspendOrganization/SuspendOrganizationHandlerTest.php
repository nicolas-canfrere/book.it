<?php

declare(strict_types=1);

namespace App\Tests\Organization\Application\UseCase\SuspendOrganization;

use App\Organization\Application\UseCase\SuspendOrganization\SuspendOrganizationCommand;
use App\Organization\Application\UseCase\SuspendOrganization\SuspendOrganizationHandler;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationStatus;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\Event\OrganizationSuspended;
use App\Shared\Domain\ValueObject\OrganizationId;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SuspendOrganizationHandlerTest extends TestCase
{
    #[Test]
    public function itSuspendsAnOrganizationAndDispatchesEvent(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $dispatcher = new FakeEventDispatcher();
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $org = Organization::register($id, new OrganizationName('Hotel Y'), new OrganizationEmail('y@hotel.fr'), new \DateTimeImmutable());
        $org->activate();
        $repo->add($org);

        $handler = new SuspendOrganizationHandler($repo, $dispatcher);
        $at = new \DateTimeImmutable('2026-06-28T00:00:00Z');
        ($handler)(new SuspendOrganizationCommand($id, $at));

        $saved = $repo->get($id);
        self::assertNotNull($saved);
        self::assertSame(OrganizationStatus::Suspended, $saved->status);

        $event = $dispatcher->getLastDispatched();
        self::assertInstanceOf(OrganizationSuspended::class, $event);
    }
}
