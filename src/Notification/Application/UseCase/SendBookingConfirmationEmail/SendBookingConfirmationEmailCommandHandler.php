<?php

declare(strict_types=1);

namespace App\Notification\Application\UseCase\SendBookingConfirmationEmail;

use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\Port\BookingConfirmationEmailSenderInterface;
use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class SendBookingConfirmationEmailCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(
        private BookerContactFetcherInterface $bookerContactFetcher,
        private ReservationDetailsFetcherInterface $reservationDetailsFetcher,
        private BookingConfirmationEmailSenderInterface $emailSender,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendBookingConfirmationEmailCommand $command): void
    {
        $bookerContact = $this->bookerContactFetcher->fetch($command->bookerId);

        if (null === $bookerContact) {
            $this->logger->warning('Booking confirmation email skipped: booker not found', [
                'bookerId' => $command->bookerId,
                'reservationId' => $command->reservationId,
            ]);

            return;
        }

        $reservationDetails = $this->reservationDetailsFetcher->fetch($command->reservationId);

        if (null === $reservationDetails) {
            $this->logger->warning('Booking confirmation email skipped: reservation not found', [
                'reservationId' => $command->reservationId,
                'bookerId' => $command->bookerId,
            ]);

            return;
        }

        $this->emailSender->send($bookerContact, $reservationDetails);

        $this->logger->info('Booking confirmation email sent', [
            'reservationId' => $command->reservationId,
            'bookerId' => $command->bookerId,
        ]);
    }
}
