<?php

declare(strict_types=1);

namespace App\Organization\Application\UseCase\RegisterOrganization;

use App\Organization\Domain\Exception\OrganizationAlreadyExistsException;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\OrganizationRegistered;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class RegisterOrganizationHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(RegisterOrganizationCommand $command): void
    {
        if ($this->repository->existsByContactEmail($command->contactEmail->value)) {
            throw new OrganizationAlreadyExistsException($command->contactEmail->value);
        }

        $organization = Organization::register(
            $command->id,
            $command->name,
            $command->contactEmail,
            $command->registeredAt,
        );

        $this->repository->add($organization);

        $this->eventDispatcher->dispatch(new OrganizationRegistered(
            organizationId: $command->id->value,
            contactEmail: $command->contactEmail->value,
            registeredAt: $command->registeredAt,
        ));
    }
}
