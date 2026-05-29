<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\EventListener;

use App\Notification\Application\UseCase\SendBookingConfirmationEmail\SendBookingConfirmationEmailCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\ReservationConfirmed;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationConfirmed::class)]
final readonly class ReservationConfirmedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(ReservationConfirmed $event): void
    {
        $this->commandDispatcher->dispatch(new SendBookingConfirmationEmailCommand(
            reservationId: $event->reservationId,
            bookerId: $event->bookerId,
        ));
    }
}
