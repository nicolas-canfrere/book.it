<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260606152610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create translation schema and translation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE SCHEMA IF NOT EXISTS translation");
        $this->addSql("
            CREATE TABLE translation.translation (
                id           UUID         NOT NULL,
                subject_type VARCHAR(50)  NOT NULL,
                subject_id   UUID         NOT NULL,
                locale       VARCHAR(10)  NOT NULL,
                text         TEXT         NOT NULL,
                created_at   TIMESTAMP    NOT NULL,
                updated_at   TIMESTAMP    NOT NULL,
                PRIMARY KEY (id),
                UNIQUE (subject_type, subject_id, locale)
            )
        ");
        $this->addSql("CREATE INDEX ON translation.translation (subject_type, subject_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE IF EXISTS translation.translation");
        $this->addSql("DROP SCHEMA IF EXISTS translation");
    }
}
