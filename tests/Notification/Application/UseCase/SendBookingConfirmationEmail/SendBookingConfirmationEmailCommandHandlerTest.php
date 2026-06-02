<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application\UseCase\SendBookingConfirmationEmail;

use App\Notification\Application\UseCase\SendBookingConfirmationEmail\SendBookingConfirmationEmailCommand;
use App\Notification\Application\UseCase\SendBookingConfirmationEmail\SendBookingConfirmationEmailCommandHandler;
use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\Port\BookingConfirmationEmailSenderInterface;
use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('unit')]
final class SendBookingConfirmationEmailCommandHandlerTest extends TestCase
{
    private BookerContactFetcherInterface $bookerContactFetcher;
    private ReservationDetailsFetcherInterface $reservationDetailsFetcher;
    /** @var BookingConfirmationEmailSenderInterface&MockObject */
    private BookingConfirmationEmailSenderInterface $emailSender;
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;
    private SendBookingConfirmationEmailCommandHandler $handler;

    protected function setUp(): void
    {
        $this->emailSender = $this->createMock(BookingConfirmationEmailSenderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    #[Test]
    public function itSendsEmailWhenBookerAndReservationExist(): void
    {
        $this->bookerContactFetcher = new class implements BookerContactFetcherInterface {
            public function fetch(string $bookerId): BookerContact
            {
                return new BookerContact('Jean', 'Dupont', 'jean.dupont@example.com');
            }
        };

        $this->reservationDetailsFetcher = new class implements ReservationDetailsFetcherInterface {
            public function fetch(string $reservationId): ReservationDetails
            {
                return new ReservationDetails(
                    new \DateTimeImmutable('2026-07-01'),
                    new \DateTimeImmutable('2026-07-05'),
                    40000,
                );
            }
        };

        $this->handler = new SendBookingConfirmationEmailCommandHandler(
            $this->bookerContactFetcher,
            $this->reservationDetailsFetcher,
            $this->emailSender,
            $this->logger,
        );

        $this->emailSender->expects($this->once())->method('send');
        $this->logger->expects($this->once())->method('info');

        ($this->handler)(new SendBookingConfirmationEmailCommand('res-001', 'booker-001'));
    }

    #[Test]
    public function itLogsWarningAndDoesNotSendWhenBookerNotFound(): void
    {
        $this->bookerContactFetcher = new class implements BookerContactFetcherInterface {
            public function fetch(string $bookerId): ?BookerContact
            {
                return null;
            }
        };

        $this->reservationDetailsFetcher = new class implements ReservationDetailsFetcherInterface {
            public function fetch(string $reservationId): ReservationDetails
            {
                return new ReservationDetails(
                    new \DateTimeImmutable('2026-07-01'),
                    new \DateTimeImmutable('2026-07-05'),
                    40000,
                );
            }
        };

        $this->handler = new SendBookingConfirmationEmailCommandHandler(
            $this->bookerContactFetcher,
            $this->reservationDetailsFetcher,
            $this->emailSender,
            $this->logger,
        );

        $this->emailSender->expects($this->never())->method('send');
        $this->logger->expects($this->once())->method('warning');

        ($this->handler)(new SendBookingConfirmationEmailCommand('res-001', 'booker-unknown'));
    }

    #[Test]
    public function itLogsWarningAndDoesNotSendWhenReservationNotFound(): void
    {
        $this->bookerContactFetcher = new class implements BookerContactFetcherInterface {
            public function fetch(string $bookerId): BookerContact
            {
                return new BookerContact('Jean', 'Dupont', 'jean.dupont@example.com');
            }
        };

        $this->reservationDetailsFetcher = new class implements ReservationDetailsFetcherInterface {
            public function fetch(string $reservationId): ?ReservationDetails
            {
                return null;
            }
        };

        $this->handler = new SendBookingConfirmationEmailCommandHandler(
            $this->bookerContactFetcher,
            $this->reservationDetailsFetcher,
            $this->emailSender,
            $this->logger,
        );

        $this->emailSender->expects($this->never())->method('send');
        $this->logger->expects($this->once())->method('warning');

        ($this->handler)(new SendBookingConfirmationEmailCommand('res-unknown', 'booker-001'));
    }
}
