<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530080053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move booker table to booker schema, assign dedicated DBAL connection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS booker');
        $this->addSql('ALTER TABLE booker SET SCHEMA booker');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booker.booker SET SCHEMA public');
        $this->addSql('DROP SCHEMA IF EXISTS booker');
    }
}
