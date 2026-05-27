<?php

declare(strict_types=1);

namespace App\Notification\Application\UseCase\SendBookingConfirmationEmail;

use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class SendBookingConfirmationEmailCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(
        private BookerContactFetcherInterface $bookerContactFetcher,
        private ReservationDetailsFetcherInterface $reservationDetailsFetcher,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerFrom,
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

        $email = (new Email())
            ->from(new Address($this->mailerFrom, 'book.it'))
            ->to(new Address($bookerContact->email, $bookerContact->firstName.' '.$bookerContact->lastName))
            ->subject('Votre réservation est confirmée')
            ->text(sprintf(
                "Bonjour %s,\n\nVotre séjour du %s au %s est bien enregistré.\nMontant total : %.2f €\n\nÀ bientôt,\nL'équipe book.it",
                $bookerContact->firstName,
                $reservationDetails->checkIn->format('d/m/Y'),
                $reservationDetails->checkOut->format('d/m/Y'),
                $reservationDetails->totalPriceCents / 100,
            ));

        $this->mailer->send($email);

        $this->logger->info('Booking confirmation email sent', [
            'reservationId' => $command->reservationId,
            'bookerId' => $command->bookerId,
        ]);
    }
}
