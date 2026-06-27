<?php

declare(strict_types=1);

namespace App\Organization\Domain\ValueObject;

final readonly class OrganizationEmail
{
    public function __construct(public string $value)
    {
        if ('' === $value || false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid organization email');
        }
    }
}
