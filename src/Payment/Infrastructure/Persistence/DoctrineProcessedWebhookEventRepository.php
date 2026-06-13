<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence;

use App\Payment\Domain\Port\ProcessedWebhookEventRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineProcessedWebhookEventRepository implements ProcessedWebhookEventRepositoryInterface
{
    public function __construct(private Connection $paymentConnection)
    {
    }

    public function record(string $eventId): bool
    {
        $this->paymentConnection->beginTransaction();

        try {
            $this->paymentConnection->executeStatement(
                'INSERT INTO webhook_events (event_id, processed_at) VALUES (:id, NOW())',
                ['id' => $eventId],
            );
            $this->paymentConnection->commit();

            return true;
        } catch (UniqueConstraintViolationException) {
            $this->paymentConnection->rollBack();

            return false;
        }
    }
}
