<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class OrganizationId
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(OrganizationId $other): bool
    {
        return $this->value === $other->value;
    }
}
