<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pricing_base_rate and pricing_rate_period tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pricing_base_rate (room_id UUID NOT NULL, amount_cents INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (room_id))');
        $this->addSql('CREATE TABLE pricing_rate_period (id UUID NOT NULL, room_id UUID NOT NULL, check_in DATE NOT NULL, check_out DATE NOT NULL, amount_cents INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_pricing_rate_period_room_id ON pricing_rate_period (room_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_pricing_rate_period_room_id');
        $this->addSql('DROP TABLE pricing_rate_period');
        $this->addSql('DROP TABLE pricing_base_rate');
    }
}
