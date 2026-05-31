<?php

declare(strict_types=1);

namespace App\Notification\Domain\Port;

use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;

interface CancellationConfirmationEmailSenderInterface
{
    public function send(BookerContact $bookerContact, ReservationDetails $reservationDetails, int $refundAmountCents): void;
}
