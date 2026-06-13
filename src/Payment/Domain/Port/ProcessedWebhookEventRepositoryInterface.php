<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port;

interface ProcessedWebhookEventRepositoryInterface
{
    /**
     * Records the event as processed.
     * Returns true if the event was new (caller should proceed).
     * Returns false if already processed (caller should skip — idempotent).
     */
    public function record(string $eventId): bool;
}
