<?php

declare(strict_types=1);

namespace App\Booker\Domain\Exception;

final class ExternalAccountCreationException extends \RuntimeException
{
    public function __construct(string $email, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Failed to create external account for "%s"', $email), 0, $previous);
    }
}
