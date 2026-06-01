<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source_id to search.unavailable_periods to support hold deletion by reservationId';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search.unavailable_periods ADD COLUMN source_id VARCHAR(36) NOT NULL DEFAULT \'\'');
        $this->addSql('CREATE INDEX idx_search_unavailable_periods_source ON search.unavailable_periods (source_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search.unavailable_periods DROP COLUMN source_id');
    }
}
