<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260522200756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stars and superior columns to hotel table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel ADD COLUMN stars SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE hotel ADD COLUMN superior BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel DROP COLUMN superior');
        $this->addSql('ALTER TABLE hotel DROP COLUMN stars');
    }
}
