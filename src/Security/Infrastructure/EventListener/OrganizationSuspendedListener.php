<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\EventListener;

use App\Shared\Domain\Event\OrganizationSuspended;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrganizationSuspended::class)]
final readonly class OrganizationSuspendedListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(OrganizationSuspended $event): void
    {
        // En V1 : pas d'opérateurs scoped par organization_id dans la DB encore.
        // Ce listener sera enrichi en sous-projet 2.
        $this->logger->info('OrganizationSuspended received', [
            'organization_id' => $event->organizationId,
            'suspended_at' => $event->suspendedAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
