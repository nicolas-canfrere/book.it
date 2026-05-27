<?php

declare(strict_types=1);

namespace App\Notification\Application\UseCase\SendBookingConfirmationEmail;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class SendBookingConfirmationEmailCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public string $bookerId,
    ) {
    }
}
