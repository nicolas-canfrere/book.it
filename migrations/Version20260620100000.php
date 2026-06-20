<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional geo_place_id to search.hotel_room_types read model';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search.hotel_room_types ADD COLUMN geo_place_id VARCHAR(255) DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.geo_place_id IS 'GeoNames id (dénormalisé depuis hotel.hotel.geo_place_id) — destiné à remplacer le filtre de recherche par ville en texte libre'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search.hotel_room_types DROP COLUMN geo_place_id');
    }
}
