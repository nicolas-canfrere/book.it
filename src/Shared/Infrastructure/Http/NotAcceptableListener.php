<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final readonly class NotAcceptableListener
{
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $accept = $event->getRequest()->headers->get('Accept');
        if (null === $accept) {
            return;
        }

        foreach ($event->getRequest()->getAcceptableContentTypes() as $type) {
            if ('*/*' === $type || 'application/*' === $type || 'application/json' === $type) {
                return;
            }
        }

        $event->setResponse(new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => 'Not Acceptable',
                'status' => Response::HTTP_NOT_ACCEPTABLE,
                'detail' => 'This API only serves application/json responses.',
            ],
            Response::HTTP_NOT_ACCEPTABLE,
            ['Content-Type' => 'application/problem+json'],
        ));
    }
}
