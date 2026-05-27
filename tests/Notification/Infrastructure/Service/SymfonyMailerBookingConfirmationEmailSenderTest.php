<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\Service;

use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use App\Notification\Infrastructure\Service\SymfonyMailerBookingConfirmationEmailSender;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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

    public function test_sends_email_with_expected_content(): void
    {
        $sender = new SymfonyMailerBookingConfirmationEmailSender($this->mailer, 'noreply@book.it');

        $bookerContact = new BookerContact('Jean', 'Dupont', 'jean.dupont@example.com');
        $reservationDetails = new ReservationDetails(
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-05'),
            40000,
        );

        /** @var Email|null $sentEmail */
        $sentEmail = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function (RawMessage $message) use (&$sentEmail): void {
                $sentEmail = $message;
            });

        $sender->send($bookerContact, $reservationDetails);

        self::assertNotNull($sentEmail);
        self::assertInstanceOf(Email::class, $sentEmail);
        self::assertSame('Votre réservation est confirmée', $sentEmail->getSubject());
        self::assertStringContainsString('jean.dupont@example.com', $sentEmail->getTo()[0]->getAddress());
        self::assertStringContainsString('noreply@book.it', $sentEmail->getFrom()[0]->getAddress());
        self::assertStringContainsString('Jean', (string) $sentEmail->getTextBody());
        self::assertStringContainsString('01/07/2026', (string) $sentEmail->getTextBody());
        self::assertStringContainsString('05/07/2026', (string) $sentEmail->getTextBody());
        self::assertStringContainsString('400.00', (string) $sentEmail->getTextBody());
    }
}
