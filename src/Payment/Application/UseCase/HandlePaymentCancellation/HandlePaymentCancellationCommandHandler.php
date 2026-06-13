<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\PaymentCancelled;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class HandlePaymentCancellationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ProcessedWebhookEventRepositoryInterface $processedEvents,
    ) {
    }

    public function __invoke(HandlePaymentCancellationCommand $command): void
    {
        if (!$this->processedEvents->record($command->eventId)) {
            return;
        }

        $this->eventDispatcher->dispatch(new PaymentCancelled($command->reservationId));
    }
}
