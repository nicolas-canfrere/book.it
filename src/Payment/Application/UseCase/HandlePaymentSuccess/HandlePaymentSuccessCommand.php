<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class HandlePaymentSuccessCommand implements SyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public string $eventId,
    ) {
    }
}
