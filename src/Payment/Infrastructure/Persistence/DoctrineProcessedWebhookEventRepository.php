<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence;

use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineProcessedWebhookEventRepository implements ProcessedWebhookEventRepositoryInterface
{
    public function __construct(private Connection $bookitConnection)
    {
    }

    public function record(string $eventId): bool
    {
        $this->bookitConnection->beginTransaction();

        try {
            $this->bookitConnection->executeStatement(
                'INSERT INTO payment.webhook_events (event_id, processed_at) VALUES (:id, NOW())',
                ['id' => $eventId],
            );
            $this->bookitConnection->commit();

            return true;
        } catch (UniqueConstraintViolationException) {
            $this->bookitConnection->rollBack();

            return false;
        }
    }
}
