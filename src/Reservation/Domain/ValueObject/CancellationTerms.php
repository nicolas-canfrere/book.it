<?php

declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

final readonly class CancellationTerms
{
    private function __construct(
        public ?int $daysThreshold,
    ) {
    }

    public static function alwaysRefundable(): self
    {
        return new self(null);
    }

    public static function withThreshold(int $days): self
    {
        if ($days <= 0) {
            throw new \InvalidArgumentException('Days threshold must be greater than zero.');
        }

        return new self($days);
    }

    public function isRefundable(\DateTimeImmutable $cancelledAt, \DateTimeImmutable $checkIn): bool
    {
        if (null === $this->daysThreshold) {
            return true;
        }

        $cancelDate = new \DateTimeImmutable($cancelledAt->format('Y-m-d'));
        $deadline = (new \DateTimeImmutable($checkIn->format('Y-m-d')))
            ->modify("-{$this->daysThreshold} days");

        return $cancelDate < $deadline;
    }
}
