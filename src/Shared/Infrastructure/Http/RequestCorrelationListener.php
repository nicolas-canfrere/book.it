<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Correlation\RequestCorrelationContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

final readonly class RequestCorrelationListener
{
    public function __construct(private RequestCorrelationContext $context)
    {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $id = $event->getRequest()->headers->get('X-Request-Id') ?? Uuid::v4()->toRfc4122();
        $this->context->setId($id);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set('X-Request-Id', $this->context->getId());
    }
}
