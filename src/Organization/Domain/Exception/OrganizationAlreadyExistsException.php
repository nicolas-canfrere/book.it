<?php

declare(strict_types=1);

namespace App\Organization\Domain\Exception;

final class OrganizationAlreadyExistsException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct("An organization with email '{$email}' already exists.");
    }
}
