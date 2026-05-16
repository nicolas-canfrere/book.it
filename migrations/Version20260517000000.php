<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename room.number to room_number, add room_floor column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE room RENAME COLUMN number TO room_number');
        $this->addSql('ALTER TABLE room ADD COLUMN room_floor INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE room ALTER COLUMN room_floor DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE room DROP COLUMN room_floor');
        $this->addSql('ALTER TABLE room RENAME COLUMN room_number TO number');
    }
}
