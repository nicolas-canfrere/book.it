<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentFailure;

use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class HandlePaymentFailureCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private ProcessedWebhookEventRepositoryInterface $processedEvents)
    {
    }

    public function __invoke(HandlePaymentFailureCommand $command): void
    {
        if (!$this->processedEvents->record($command->eventId)) {
            return;
        }
    }
}
