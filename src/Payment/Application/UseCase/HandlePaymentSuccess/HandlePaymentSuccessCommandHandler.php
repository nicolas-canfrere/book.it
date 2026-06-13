<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\PaymentConfirmed;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class HandlePaymentSuccessCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ProcessedWebhookEventRepositoryInterface $processedEvents,
    ) {
    }

    public function __invoke(HandlePaymentSuccessCommand $command): void
    {
        if (!$this->processedEvents->record($command->eventId)) {
            return;
        }

        $this->eventDispatcher->dispatch(new PaymentConfirmed($command->reservationId));
    }
}
