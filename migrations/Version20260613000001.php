<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payment schema and webhook_events idempotency table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS payment');
        $this->addSql('CREATE TABLE payment.webhook_events (
            event_id UUID NOT NULL,
            processed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW(),
            PRIMARY KEY (event_id)
        )');
        $this->addSql("COMMENT ON TABLE payment.webhook_events IS 'Idempotency log for payment provider webhooks — prevents duplicate processing of the same event_id.'");
        $this->addSql("COMMENT ON COLUMN payment.webhook_events.event_id IS 'UUID v4 sent by the payment provider — acts as the idempotency key'");
        $this->addSql("COMMENT ON COLUMN payment.webhook_events.processed_at IS 'Timestamp when this event was first processed'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payment.webhook_events');
        $this->addSql('DROP SCHEMA IF EXISTS payment');
    }
}
