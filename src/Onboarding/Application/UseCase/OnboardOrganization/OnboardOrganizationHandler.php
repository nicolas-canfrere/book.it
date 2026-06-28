<?php

declare(strict_types=1);

namespace App\Onboarding\Application\UseCase\OnboardOrganization;

use App\Onboarding\Application\Port\OrganizationRegistrarInterface;
use App\Onboarding\Application\Port\OwnerOperatorRegistrarInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class OnboardOrganizationHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private OrganizationRegistrarInterface $organizationRegistrar,
        private OwnerOperatorRegistrarInterface $ownerRegistrar,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(OnboardOrganizationCommand $command): void
    {
        $this->organizationRegistrar->register(
            $command->organizationId,
            $command->organizationName,
            $command->contactEmail,
            $command->registeredAt,
        );

        try {
            $this->ownerRegistrar->registerOwner(
                $command->operatorId,
                $command->ownerFirstName,
                $command->ownerLastName,
                $command->contactEmail,
                $command->ownerPhone,
                $command->password,
                $command->organizationId,
                $command->registeredAt,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Owner registration failed — compensating by removing organization', [
                'organizationId' => $command->organizationId,
                'error' => $e->getMessage(),
            ]);
            $this->organizationRegistrar->removeOrganization($command->organizationId);

            throw $e;
        }
    }
}
