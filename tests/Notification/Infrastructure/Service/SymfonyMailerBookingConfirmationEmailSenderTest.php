<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\Service;

use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use App\Notification\Infrastructure\Service\SymfonyMailerBookingConfirmationEmailSender;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

#[Group('unit')]
final class SymfonyMailerBookingConfirmationEmailSenderTest extends TestCase
{
    /** @var MailerInterface&MockObject */
    private MailerInterface $mailer;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
    }

    #[Test]
    public function itSendsTemplatedEmailWithCorrectTemplatesAndContext(): void
    {
        $sender = new SymfonyMailerBookingConfirmationEmailSender($this->mailer, 'noreply@book.it');

        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');
        $bookerContact = new BookerContact('Jean', 'Dupont', 'jean.dupont@example.com');
        $reservationDetails = new ReservationDetails($checkIn, $checkOut, 40000);

        /** @var TemplatedEmail|null $sentEmail */
        $sentEmail = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function (RawMessage $message) use (&$sentEmail): void {
                $sentEmail = $message;
            });

        $sender->send($bookerContact, $reservationDetails);

        self::assertNotNull($sentEmail);
        self::assertInstanceOf(TemplatedEmail::class, $sentEmail);
        self::assertSame('Votre réservation est confirmée', $sentEmail->getSubject());
        self::assertStringContainsString('jean.dupont@example.com', $sentEmail->getTo()[0]->getAddress());
        self::assertStringContainsString('noreply@book.it', $sentEmail->getFrom()[0]->getAddress());
        self::assertSame('emails/booking_confirmation.html.twig', $sentEmail->getHtmlTemplate());
        self::assertSame('emails/booking_confirmation.txt.twig', $sentEmail->getTextTemplate());
        $context = $sentEmail->getContext();
        self::assertSame('Jean', $context['firstName']);
        self::assertSame($checkIn, $context['checkIn']);
        self::assertSame($checkOut, $context['checkOut']);
        self::assertSame(40000, $context['totalPriceCents']);
    }
}
