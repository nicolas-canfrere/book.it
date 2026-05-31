<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\EventListener;

use App\Shared\Domain\Event\ReservationCancelled;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationCancelled::class)]
final readonly class ReservationCancelledListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReservationCancelled $event): void
    {
        $this->logger->info('Reservation cancelled — refund to process', [
            'reservationId' => $event->reservationId,
            'bookerId' => $event->bookerId,
            'refundAmountCents' => $event->refundAmountCents,
        ]);
    }
}
