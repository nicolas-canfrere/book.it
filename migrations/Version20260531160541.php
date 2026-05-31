<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531160541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add amenities column to room.room_type table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE room.room_type ADD COLUMN amenities text[] NOT NULL DEFAULT '{}'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE room.room_type DROP COLUMN amenities');
    }
}
