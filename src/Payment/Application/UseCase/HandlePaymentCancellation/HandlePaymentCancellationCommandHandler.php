<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Payment\Domain\Port\ReservationPaymentCancellerInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class HandlePaymentCancellationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private ReservationPaymentCancellerInterface $canceller)
    {
    }

    public function __invoke(HandlePaymentCancellationCommand $command): void
    {
        $this->canceller->cancel($command->reservationId);
    }
}
