<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class PaymentConfirmed
{
    public function __construct(
        public string $reservationId,
    ) {
    }
}
