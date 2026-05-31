<?php

declare(strict_types=1);

namespace App\Notification\Application\UseCase\SendCancellationConfirmationEmail;

use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\Port\CancellationConfirmationEmailSenderInterface;
use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class SendCancellationConfirmationEmailCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(
        private BookerContactFetcherInterface $bookerContactFetcher,
        private ReservationDetailsFetcherInterface $reservationDetailsFetcher,
        private CancellationConfirmationEmailSenderInterface $emailSender,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendCancellationConfirmationEmailCommand $command): void
    {
        $bookerContact = $this->bookerContactFetcher->fetch($command->bookerId);

        if (null === $bookerContact) {
            $this->logger->warning('Cancellation confirmation email skipped: booker not found', [
                'bookerId' => $command->bookerId,
                'reservationId' => $command->reservationId,
            ]);

            return;
        }

        $reservationDetails = $this->reservationDetailsFetcher->fetch($command->reservationId);

        if (null === $reservationDetails) {
            $this->logger->warning('Cancellation confirmation email skipped: reservation not found', [
                'reservationId' => $command->reservationId,
                'bookerId' => $command->bookerId,
            ]);

            return;
        }

        $this->emailSender->send($bookerContact, $reservationDetails, $command->refundAmountCents);

        $this->logger->info('Cancellation confirmation email sent', [
            'reservationId' => $command->reservationId,
            'bookerId' => $command->bookerId,
        ]);
    }
}
