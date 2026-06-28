<?php

declare(strict_types=1);

namespace App\Organization\Application\UseCase\SuspendOrganization;

use App\Organization\Domain\Exception\OrganizationNotFoundException;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\OrganizationSuspended;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class SuspendOrganizationHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(SuspendOrganizationCommand $command): void
    {
        $organization = $this->repository->get($command->id);
        if (null === $organization) {
            throw new OrganizationNotFoundException($command->id);
        }

        $organization->suspend();
        $this->repository->save($organization);

        $this->eventDispatcher->dispatch(new OrganizationSuspended(
            organizationId: $organization->id->value,
            suspendedAt: $command->suspendedAt,
        ));
    }
}
