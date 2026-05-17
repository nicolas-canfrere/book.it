<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

final readonly class Money
{
    public function __construct(
        public int $amountCents,
    ) {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Amount in cents must be greater than zero.');
        }
    }

    public static function fromEuros(float $euros): self
    {
        return new self((int) round($euros * 100));
    }

    public function toEuros(): float
    {
        return $this->amountCents / 100;
    }
}
