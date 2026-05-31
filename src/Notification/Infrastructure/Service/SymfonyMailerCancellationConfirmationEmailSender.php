<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\CancellationConfirmationEmailSenderInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class SymfonyMailerCancellationConfirmationEmailSender implements CancellationConfirmationEmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $mailerFrom,
    ) {
    }

    public function send(BookerContact $bookerContact, ReservationDetails $reservationDetails, int $refundAmountCents): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'book.it'))
            ->to(new Address($bookerContact->email, $bookerContact->firstName . ' ' . $bookerContact->lastName))
            ->subject('Votre réservation a été annulée')
            ->textTemplate('emails/cancellation_confirmation.txt.twig')
            ->htmlTemplate('emails/cancellation_confirmation.html.twig')
            ->context([
                'firstName' => $bookerContact->firstName,
                'checkIn' => $reservationDetails->checkIn,
                'checkOut' => $reservationDetails->checkOut,
                'refundAmountCents' => $refundAmountCents,
            ]);

        $this->mailer->send($email);
    }
}
