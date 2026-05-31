<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531084119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move reservation tables to reservation schema, rename reservation_guest to guest';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS reservation');
        $this->addSql('ALTER TABLE reservation SET SCHEMA reservation');
        $this->addSql('ALTER TABLE reservation_guest SET SCHEMA reservation');
        $this->addSql('ALTER TABLE reservation.reservation_guest RENAME TO guest');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation.guest RENAME TO reservation_guest');
        $this->addSql('ALTER TABLE reservation.reservation_guest SET SCHEMA public');
        $this->addSql('ALTER TABLE reservation.reservation SET SCHEMA public');
        $this->addSql('DROP SCHEMA IF EXISTS reservation');
    }
}
