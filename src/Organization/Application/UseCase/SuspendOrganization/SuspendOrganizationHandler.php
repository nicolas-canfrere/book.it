<?php

declare(strict_types=1);

namespace App\Organization\Application\UseCase\SuspendOrganization;

use App\Organization\Domain\Exception\OrganizationNotFoundException;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
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

        $organization->suspend($command->suspendedAt);
        $this->repository->save($organization);

        foreach ($organization->pullEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
