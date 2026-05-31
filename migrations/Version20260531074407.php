<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531074407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cancelled_at and cancelled_by columns to reservation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD cancelled_at DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD cancelled_by VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP COLUMN cancelled_at');
        $this->addSql('ALTER TABLE reservation DROP COLUMN cancelled_by');
    }
}
