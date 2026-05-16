<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516154122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create blocked_period table for Availability context';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE blocked_period (
            id UUID NOT NULL,
            room_id UUID NOT NULL,
            check_in DATE NOT NULL,
            check_out DATE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX idx_blocked_period_room_id ON blocked_period (room_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE blocked_period');
    }
}
