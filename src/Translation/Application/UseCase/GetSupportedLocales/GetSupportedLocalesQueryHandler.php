<?php

declare(strict_types=1);

namespace App\Translation\Application\UseCase\GetSupportedLocales;

use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetSupportedLocalesQueryHandler implements SyncQueryHandlerInterface
{
    /** @param list<string> $supportedLocales */
    public function __construct(
        private array $supportedLocales,
        private string $defaultLocale,
    ) {
    }

    public function __invoke(GetSupportedLocalesQuery $query): SupportedLocalesView
    {
        return new SupportedLocalesView($this->supportedLocales, $this->defaultLocale);
    }
}
