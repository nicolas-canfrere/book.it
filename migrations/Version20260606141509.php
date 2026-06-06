<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260606141509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS operator');
        $this->addSql('CREATE TABLE operator.operator (
        id           UUID         NOT NULL PRIMARY KEY,
        first_name   VARCHAR(100) NOT NULL,
        last_name    VARCHAR(100) NOT NULL,
        email        VARCHAR(255) NOT NULL UNIQUE,
        phone        VARCHAR(50)  NOT NULL,
        registered_at TIMESTAMP   NOT NULL
    )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE operator.operator');
        $this->addSql('DROP SCHEMA IF EXISTS operator');
    }
}
