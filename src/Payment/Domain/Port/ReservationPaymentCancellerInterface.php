<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port;

interface ReservationPaymentCancellerInterface
{
    public function cancel(string $reservationId): void;
}
