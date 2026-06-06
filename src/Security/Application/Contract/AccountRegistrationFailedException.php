<?php

declare(strict_types=1);

namespace App\Security\Application\Contract;

final class AccountRegistrationFailedException extends \RuntimeException
{
    public function __construct(string $email, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Failed to register account for "%s"', $email), 0, $previous);
    }
}
