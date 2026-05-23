<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Payment\Domain\Port\ReservationPaymentConfirmerInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class HandlePaymentSuccessCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private ReservationPaymentConfirmerInterface $confirmer)
    {
    }

    public function __invoke(HandlePaymentSuccessCommand $command): void
    {
        $this->confirmer->confirm($command->reservationId);
    }
}
