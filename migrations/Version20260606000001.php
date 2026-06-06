<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create security schema and identity_mapping table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS security');
        $this->addSql('CREATE TABLE security.identity_mapping (
            internal_id UUID        NOT NULL,
            context     VARCHAR(50) NOT NULL,
            external_id UUID        NOT NULL,
            PRIMARY KEY (internal_id, context)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE security.identity_mapping');
        $this->addSql('DROP SCHEMA IF EXISTS security');
    }
}
