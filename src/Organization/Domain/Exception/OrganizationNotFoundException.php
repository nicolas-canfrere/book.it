<?php

declare(strict_types=1);

namespace App\Organization\Domain\Exception;

use App\Shared\Domain\ValueObject\OrganizationId;

final class OrganizationNotFoundException extends \DomainException
{
    public function __construct(OrganizationId $id)
    {
        parent::__construct("Organization '{$id->value}' not found.");
    }
}
