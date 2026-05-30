<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move room and room_type tables to room schema, assign dedicated DBAL connection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS room');
        $this->addSql('ALTER TABLE room SET SCHEMA room');
        $this->addSql('ALTER TABLE room_type SET SCHEMA room');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE room.room_type SET SCHEMA public');
        $this->addSql('ALTER TABLE room.room SET SCHEMA public');
        $this->addSql('DROP SCHEMA IF EXISTS room');
    }
}
