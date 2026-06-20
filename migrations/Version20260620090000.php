<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional geo_place_id to hotel.hotel table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel.hotel ADD COLUMN geo_place_id VARCHAR(255) DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN hotel.hotel.geo_place_id IS 'GeoNames id disambiguating the free-text city — validated against geo.geo_place at registration, not a foreign key (contexts stay decoupled at the DB level)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel.hotel DROP COLUMN geo_place_id');
    }
}
