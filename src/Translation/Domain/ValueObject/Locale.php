<?php

declare(strict_types=1);

namespace App\Translation\Domain\ValueObject;

final readonly class Locale
{
    public function __construct(public string $value)
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('Locale cannot be empty.');
        }
        if (strlen($value) > 10) {
            throw new \InvalidArgumentException('Locale must not exceed 10 characters.');
        }
        if (!preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $value)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid locale format.', $value));
        }
    }
}
