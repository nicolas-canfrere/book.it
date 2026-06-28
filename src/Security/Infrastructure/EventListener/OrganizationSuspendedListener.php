<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\EventListener;

use App\Security\Infrastructure\Keycloak\KeycloakHttpClientInterface;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use App\Shared\Domain\Event\OrganizationSuspended;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrganizationSuspended::class)]
final readonly class OrganizationSuspendedListener
{
    public function __construct(
        private KeycloakHttpClientInterface $keycloakClient,
        private IdentityMappingRepository $identityMapping,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrganizationSuspended $event): void
    {
        // Trouver tous les opérateurs de cette organisation et désactiver leurs comptes Keycloak
        // En V1 : pas d'opérateurs scoped par organization_id dans la DB encore (pas de query par org)
        // Ce listener sera enrichi en sous-projet 2.
        $this->logger->info('OrganizationSuspended received', [
            'organization_id' => $event->organizationId,
            'suspended_at'    => $event->suspendedAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
