<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260519225412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add price_breakdown JSONB column to reservation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE reservation ADD COLUMN price_breakdown JSONB NOT NULL DEFAULT '[]'::jsonb");
        $this->addSql('ALTER TABLE reservation ALTER COLUMN price_breakdown DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP COLUMN price_breakdown');
    }
}
