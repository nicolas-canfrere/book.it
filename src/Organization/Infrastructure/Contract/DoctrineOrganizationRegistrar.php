<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Contract;

use App\Organization\Application\Contract\OrganizationRegistrarInterface;
use App\Organization\Domain\Exception\OrganizationAlreadyExistsException;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\ValueObject\OrganizationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

final readonly class DoctrineOrganizationRegistrar implements OrganizationRegistrarInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function register(
        string $organizationId,
        string $name,
        string $contactEmail,
        \DateTimeImmutable $registeredAt,
    ): void {
        if ($this->repository->existsByContactEmail($contactEmail)) {
            throw new OrganizationAlreadyExistsException($contactEmail);
        }

        $organization = Organization::register(
            new OrganizationId($organizationId),
            new OrganizationName($name),
            new OrganizationEmail($contactEmail),
            $registeredAt,
        );

        $this->repository->add($organization);

        foreach ($organization->pullEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function remove(string $organizationId): void
    {
        try {
            $this->repository->remove(new OrganizationId($organizationId));
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to remove organization during compensation', [
                'organizationId' => $organizationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
