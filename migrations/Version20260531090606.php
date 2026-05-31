<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531090606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move availability tables to availability schema, rename to drop context prefix';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS availability');
        $this->addSql('ALTER TABLE availability_hold SET SCHEMA availability');
        $this->addSql('ALTER TABLE availability.availability_hold RENAME TO hold');
        $this->addSql('ALTER TABLE blocked_period SET SCHEMA availability');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE availability.blocked_period SET SCHEMA public');
        $this->addSql('ALTER TABLE availability.hold RENAME TO availability_hold');
        $this->addSql('ALTER TABLE availability.availability_hold SET SCHEMA public');
        $this->addSql('DROP SCHEMA IF EXISTS availability');
    }
}
