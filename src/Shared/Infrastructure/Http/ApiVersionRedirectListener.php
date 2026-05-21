<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 33)]
final readonly class ApiVersionRedirectListener
{
    /** @param array<string, string> $deprecatedVersions version => sunset date (Y-m-d) */
    public function __construct(
        private string $currentApiVersion,
        private array $deprecatedVersions,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (preg_match('#^/api/(v\d+)/(.*)#', $path, $matches)) {
            $this->handleVersionedPath($event, $matches[1]);

            return;
        }

        if (preg_match('#^/api/(?!v\d+/|doc)(.*)#', $path, $matches)) {
            $event->setResponse(new RedirectResponse(
                '/api/' . $this->currentApiVersion . '/' . $matches[1],
                Response::HTTP_TEMPORARY_REDIRECT,
            ));
        }
    }

    private function handleVersionedPath(RequestEvent $event, string $version): void
    {
        if (!isset($this->deprecatedVersions[$version])) {
            return;
        }

        $sunset = new \DateTimeImmutable($this->deprecatedVersions[$version]);

        if (new \DateTimeImmutable() < $sunset) {
            return;
        }

        $event->setResponse(new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => 'Gone',
                'status' => Response::HTTP_GONE,
                'detail' => sprintf(
                    'API %s has been removed since %s. Please migrate to /api/%s/.',
                    $version,
                    $sunset->format('Y-m-d'),
                    $this->currentApiVersion,
                ),
            ],
            Response::HTTP_GONE,
            ['Content-Type' => 'application/problem+json'],
        ));
    }
}
