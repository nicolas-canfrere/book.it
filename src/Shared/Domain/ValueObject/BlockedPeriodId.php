<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class BlockedPeriodId
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
