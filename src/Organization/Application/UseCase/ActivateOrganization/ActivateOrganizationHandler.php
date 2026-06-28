<?php

declare(strict_types=1);

namespace App\Organization\Application\UseCase\ActivateOrganization;

use App\Organization\Domain\Exception\OrganizationNotFoundException;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class ActivateOrganizationHandler implements SyncCommandHandlerInterface
{
    public function __construct(private OrganizationRepositoryInterface $repository)
    {
    }

    public function __invoke(ActivateOrganizationCommand $command): void
    {
        $organization = $this->repository->get($command->id);
        if (null === $organization) {
            throw new OrganizationNotFoundException($command->id);
        }

        $organization->activate();
        $this->repository->save($organization);
    }
}
