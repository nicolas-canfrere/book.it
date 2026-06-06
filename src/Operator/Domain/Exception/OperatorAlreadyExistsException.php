<?php

declare(strict_types=1);

namespace App\Operator\Domain\Exception;

final class OperatorAlreadyExistsException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('An operator with email "%s" already exists.', $email));
    }
}
