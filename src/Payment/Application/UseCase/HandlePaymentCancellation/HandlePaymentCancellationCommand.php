<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class HandlePaymentCancellationCommand implements SyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public string $eventId,
    ) {
    }
}
