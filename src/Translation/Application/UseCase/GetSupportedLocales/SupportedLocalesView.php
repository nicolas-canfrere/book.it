<?php

declare(strict_types=1);

namespace App\Translation\Application\UseCase\GetSupportedLocales;

final readonly class SupportedLocalesView
{
    /** @param list<string> $supported */
    public function __construct(
        public array $supported,
        public string $default,
    ) {
    }
}
