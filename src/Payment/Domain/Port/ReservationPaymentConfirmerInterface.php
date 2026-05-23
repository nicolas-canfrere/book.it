<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port;

interface ReservationPaymentConfirmerInterface
{
    public function confirm(string $reservationId): void;
}
