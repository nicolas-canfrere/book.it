<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\EventListener;

use App\Security\Infrastructure\Keycloak\KeycloakHttpClientInterface;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use App\Shared\Domain\Event\OrganizationRegistered;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrganizationRegistered::class)]
final readonly class OrganizationRegisteredListener
{
    public function __construct(
        private KeycloakHttpClientInterface $keycloakClient,
        private IdentityMappingRepository $identityMapping,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrganizationRegistered $event): void
    {
        // En V1 : aucun opérateur n'est encore créé à ce stade.
        // Ce listener sera activé pleinement en sous-projet 2 (Onboarding)
        // quand un OrganizationOwner sera créé en même temps que l'Organization.
        $this->logger->info('OrganizationRegistered received, no operator to map in V1', [
            'organization_id' => $event->organizationId,
        ]);
    }
}
