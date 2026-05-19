<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260519173454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create availability_hold table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE availability_hold (
                id VARCHAR(36) NOT NULL,
                room_id VARCHAR(36) NOT NULL,
                reservation_id VARCHAR(36) NOT NULL,
                check_in DATE NOT NULL,
                check_out DATE NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_availability_hold_room_dates ON availability_hold (room_id, check_in, check_out, expires_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_availability_hold_reservation ON availability_hold (reservation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE availability_hold');
    }
}
