<?php

declare(strict_types=1);

namespace App\Tests\Organization\Application\UseCase\RegisterOrganization;

use App\Organization\Application\UseCase\RegisterOrganization\RegisterOrganizationCommand;
use App\Organization\Application\UseCase\RegisterOrganization\RegisterOrganizationHandler;
use App\Organization\Domain\Exception\OrganizationAlreadyExistsException;
use App\Organization\Domain\Model\OrganizationStatus;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\Event\OrganizationRegistered;
use App\Shared\Domain\ValueObject\OrganizationId;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterOrganizationHandlerTest extends TestCase
{
    private InMemoryOrganizationRepository $repository;
    private FakeEventDispatcher $dispatcher;
    private RegisterOrganizationHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryOrganizationRepository();
        $this->dispatcher = new FakeEventDispatcher();
        $this->handler = new RegisterOrganizationHandler($this->repository, $this->dispatcher);
    }

    #[Test]
    public function itRegistersAnOrganizationAndDispatchesEvent(): void
    {
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $at = new \DateTimeImmutable('2026-06-27T10:00:00Z');

        ($this->handler)(new RegisterOrganizationCommand(
            id: $id,
            name: new OrganizationName('Hotel ABC'),
            contactEmail: new OrganizationEmail('abc@hotel.fr'),
            registeredAt: $at,
        ));

        $saved = $this->repository->get($id);
        self::assertNotNull($saved);
        self::assertSame(OrganizationStatus::Pending, $saved->status);

        $event = $this->dispatcher->getLastDispatched();
        self::assertInstanceOf(OrganizationRegistered::class, $event);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $event->organizationId);
        self::assertSame('abc@hotel.fr', $event->contactEmail);
    }

    #[Test]
    public function itRejectsADuplicateEmail(): void
    {
        $id1 = new OrganizationId('550e8400-e29b-41d4-a716-446655440001');
        $id2 = new OrganizationId('550e8400-e29b-41d4-a716-446655440002');
        $at = new \DateTimeImmutable('2026-06-27T10:00:00Z');

        ($this->handler)(new RegisterOrganizationCommand($id1, new OrganizationName('Hotel A'), new OrganizationEmail('same@hotel.fr'), $at));

        $this->expectException(OrganizationAlreadyExistsException::class);
        ($this->handler)(new RegisterOrganizationCommand($id2, new OrganizationName('Hotel B'), new OrganizationEmail('same@hotel.fr'), $at));
    }
}
