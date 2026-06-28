<?php

declare(strict_types=1);

namespace App\Organization\Domain\ValueObject;

final readonly class OrganizationName
{
    public function __construct(public string $value)
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException('Organization name cannot be empty');
        }
        if (strlen($value) > 255) {
            throw new \InvalidArgumentException('Organization name cannot exceed 255 characters');
        }
    }
}
