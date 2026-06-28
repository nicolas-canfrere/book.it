<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Security\Infrastructure\Keycloak\OperatorUser;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\OrganizationId;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class TenantContextMiddleware implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private TenantContext $tenantContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof OperatorUser) {
            return;
        }

        if (null !== $user->organizationId) {
            $this->tenantContext->set(new OrganizationId($user->organizationId));
        }
    }
}
