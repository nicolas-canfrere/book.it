<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index search.hotel_room_types.geo_place_id — now the sole filter for availability search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_search_hotel_room_types_geo_place_id ON search.hotel_room_types (geo_place_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_search_hotel_room_types_geo_place_id');
    }
}
