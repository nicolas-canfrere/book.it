<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531145506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add amenities column to hotel.hotel table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE hotel.hotel ADD COLUMN amenities text[] NOT NULL DEFAULT '{}'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel.hotel DROP COLUMN amenities');
    }
}
