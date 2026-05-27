<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\BookingConfirmationEmailSenderInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class SymfonyMailerBookingConfirmationEmailSender implements BookingConfirmationEmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $mailerFrom,
    ) {
    }

    public function send(BookerContact $bookerContact, ReservationDetails $reservationDetails): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'book.it'))
            ->to(new Address($bookerContact->email, $bookerContact->firstName . ' ' . $bookerContact->lastName))
            ->subject('Votre réservation est confirmée')
            ->textTemplate('emails/booking_confirmation.txt.twig')
            ->htmlTemplate('emails/booking_confirmation.html.twig')
            ->context([
                'firstName' => $bookerContact->firstName,
                'checkIn' => $reservationDetails->checkIn,
                'checkOut' => $reservationDetails->checkOut,
                'totalPriceCents' => $reservationDetails->totalPriceCents,
            ]);

        $this->mailer->send($email);
    }
}
