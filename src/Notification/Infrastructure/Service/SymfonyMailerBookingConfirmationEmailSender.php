<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\BookingConfirmationEmailSenderInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class SymfonyMailerBookingConfirmationEmailSender implements BookingConfirmationEmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $mailerFrom,
    ) {
    }

    public function send(BookerContact $bookerContact, ReservationDetails $reservationDetails): void
    {
        $email = (new Email())
            ->from(new Address($this->mailerFrom, 'book.it'))
            ->to(new Address($bookerContact->email, $bookerContact->firstName . ' ' . $bookerContact->lastName))
            ->subject('Votre réservation est confirmée')
            ->text(sprintf(
                "Bonjour %s,\n\nVotre séjour du %s au %s est bien enregistré.\nMontant total : %.2f €\n\nÀ bientôt,\nL'équipe book.it",
                $bookerContact->firstName,
                $reservationDetails->checkIn->format('d/m/Y'),
                $reservationDetails->checkOut->format('d/m/Y'),
                $reservationDetails->totalPriceCents / 100,
            ));

        $this->mailer->send($email);
    }
}
