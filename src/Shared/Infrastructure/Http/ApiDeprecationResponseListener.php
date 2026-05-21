<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class ApiDeprecationResponseListener
{
    /** @param array<string, string> $deprecatedVersions version => sunset date (Y-m-d) */
    public function __construct(
        private string $currentApiVersion,
        private array $deprecatedVersions,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (!preg_match('#^/api/(v\d+)/(.*)#', $path, $matches)) {
            return;
        }

        $version = $matches[1];
        $remainingPath = $matches[2];

        $response = $event->getResponse();
        $response->headers->set('X-API-Version', $version);

        if (!isset($this->deprecatedVersions[$version])) {
            return;
        }

        $sunset = new \DateTimeImmutable($this->deprecatedVersions[$version]);

        if (new \DateTimeImmutable() >= $sunset) {
            return;
        }

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', $sunset->setTimezone(new \DateTimeZone('UTC'))->format('D, d M Y H:i:s \G\M\T'));
        $response->headers->set(
            'Link',
            sprintf('</api/%s/%s>; rel="successor-version"', $this->currentApiVersion, $remainingPath),
        );
    }
}
