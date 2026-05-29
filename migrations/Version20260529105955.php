<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260529105955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move hotel table to hotel schema, assign dedicated DBAL connection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS hotel');
        $this->addSql('ALTER TABLE hotel SET SCHEMA hotel');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel SET SCHEMA public');
        $this->addSql('DROP SCHEMA IF EXISTS hotel');
    }
}
