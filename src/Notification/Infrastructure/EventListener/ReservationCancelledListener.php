<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\EventListener;

use App\Notification\Application\UseCase\SendCancellationConfirmationEmail\SendCancellationConfirmationEmailCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\ReservationCancelled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationCancelled::class)]
final readonly class ReservationCancelledListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(ReservationCancelled $event): void
    {
        $this->commandDispatcher->dispatch(new SendCancellationConfirmationEmailCommand(
            reservationId: $event->reservationId,
            bookerId: $event->bookerId,
            refundAmountCents: $event->refundAmountCents,
        ));
    }
}
