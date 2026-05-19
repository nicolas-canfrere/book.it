<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cancellation_terms_days_threshold column to reservation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD COLUMN cancellation_terms_days_threshold INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP COLUMN cancellation_terms_days_threshold');
    }
}
