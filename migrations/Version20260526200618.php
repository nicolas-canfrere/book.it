<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260526200618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add room_type_id column to room table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM room');
        $this->addSql('ALTER TABLE room ADD COLUMN room_type_id UUID NOT NULL REFERENCES room_type(id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE room DROP COLUMN room_type_id');
    }
}
