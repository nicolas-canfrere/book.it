<?php

declare(strict_types=1);

namespace App\Notification\Application\UseCase\SendCancellationConfirmationEmail;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class SendCancellationConfirmationEmailCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public string $bookerId,
        public int $refundAmountCents,
    ) {
    }
}
