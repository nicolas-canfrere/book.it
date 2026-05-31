<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application\UseCase\SendCancellationConfirmationEmail;

use App\Notification\Application\UseCase\SendCancellationConfirmationEmail\SendCancellationConfirmationEmailCommand;
use App\Notification\Application\UseCase\SendCancellationConfirmationEmail\SendCancellationConfirmationEmailCommandHandler;
use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\Port\CancellationConfirmationEmailSenderInterface;
use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('unit')]
final class SendCancellationConfirmationEmailCommandHandlerTest extends TestCase
{
    private BookerContactFetcherInterface&MockObject $bookerContactFetcher;
    private ReservationDetailsFetcherInterface&MockObject $reservationDetailsFetcher;
    private CancellationConfirmationEmailSenderInterface&MockObject $emailSender;
    private LoggerInterface&MockObject $logger;
    private SendCancellationConfirmationEmailCommandHandler $handler;

    protected function setUp(): void
    {
        $this->bookerContactFetcher = $this->createMock(BookerContactFetcherInterface::class);
        $this->reservationDetailsFetcher = $this->createMock(ReservationDetailsFetcherInterface::class);
        $this->emailSender = $this->createMock(CancellationConfirmationEmailSenderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SendCancellationConfirmationEmailCommandHandler(
            $this->bookerContactFetcher,
            $this->reservationDetailsFetcher,
            $this->emailSender,
            $this->logger,
        );
    }

    public function testSendsEmailWithRefund(): void
    {
        $command = new SendCancellationConfirmationEmailCommand(
            reservationId: 'res-1',
            bookerId: 'booker-1',
            refundAmountCents: 15000,
        );

        $contact = new BookerContact('Jean', 'Dupont', 'jean@example.com');
        $details = new ReservationDetails(
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-05'),
            15000,
        );

        $this->bookerContactFetcher->expects($this->once())->method('fetch')->with('booker-1')->willReturn($contact);
        $this->reservationDetailsFetcher->expects($this->once())->method('fetch')->with('res-1')->willReturn($details);
        $this->emailSender->expects($this->once())->method('send')->with($contact, $details, 15000);
        $this->logger->expects($this->once())->method('info');

        ($this->handler)($command);
    }

    public function testSendsEmailWithoutRefund(): void
    {
        $command = new SendCancellationConfirmationEmailCommand(
            reservationId: 'res-2',
            bookerId: 'booker-2',
            refundAmountCents: 0,
        );

        $contact = new BookerContact('Marie', 'Martin', 'marie@example.com');
        $details = new ReservationDetails(
            new \DateTimeImmutable('2026-08-01'),
            new \DateTimeImmutable('2026-08-03'),
            20000,
        );

        $this->bookerContactFetcher->expects($this->once())->method('fetch')->willReturn($contact);
        $this->reservationDetailsFetcher->expects($this->once())->method('fetch')->willReturn($details);
        $this->emailSender->expects($this->once())->method('send')->with($contact, $details, 0);
        $this->logger->expects($this->once())->method('info');

        ($this->handler)($command);
    }

    public function testSkipsWhenBookerNotFound(): void
    {
        $command = new SendCancellationConfirmationEmailCommand(
            reservationId: 'res-3',
            bookerId: 'unknown',
            refundAmountCents: 0,
        );

        $this->bookerContactFetcher->expects($this->once())->method('fetch')->willReturn(null);
        $this->reservationDetailsFetcher->expects($this->never())->method('fetch');
        $this->emailSender->expects($this->never())->method('send');
        $this->logger->expects($this->once())->method('warning');

        ($this->handler)($command);
    }

    public function testSkipsWhenReservationNotFound(): void
    {
        $command = new SendCancellationConfirmationEmailCommand(
            reservationId: 'unknown',
            bookerId: 'booker-1',
            refundAmountCents: 0,
        );

        $contact = new BookerContact('Jean', 'Dupont', 'jean@example.com');
        $this->bookerContactFetcher->expects($this->once())->method('fetch')->willReturn($contact);
        $this->reservationDetailsFetcher->expects($this->once())->method('fetch')->willReturn(null);
        $this->emailSender->expects($this->never())->method('send');
        $this->logger->expects($this->once())->method('warning');

        ($this->handler)($command);
    }
}
