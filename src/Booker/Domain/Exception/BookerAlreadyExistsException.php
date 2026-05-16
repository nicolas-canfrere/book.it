<?php

declare(strict_types=1);

namespace App\Booker\Domain\Exception;

final class BookerAlreadyExistsException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(\sprintf('A booker with email "%s" already exists.', $email));
    }
}
