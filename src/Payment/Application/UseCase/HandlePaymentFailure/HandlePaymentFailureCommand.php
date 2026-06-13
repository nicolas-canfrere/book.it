<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentFailure;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class HandlePaymentFailureCommand implements SyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public string $eventId,
    ) {
    }
}
