<?php

declare(strict_types=1);

namespace App\Translation\Domain\Exception;

final class UnsupportedLocaleException extends \DomainException
{
    public function __construct(string $locale)
    {
        parent::__construct(sprintf('Locale "%s" is not supported.', $locale));
    }
}
