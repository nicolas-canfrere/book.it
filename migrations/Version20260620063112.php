<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260620063112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Geo context schema and geo_place referential table with pg_trgm fuzzy search support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS geo');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $this->addSql(<<<'SQL'
            CREATE TABLE geo.geo_place (
                geoname_id   BIGINT       NOT NULL,
                name         VARCHAR(255) NOT NULL,
                ascii_name   VARCHAR(255) NOT NULL,
                country_code VARCHAR(2)   NOT NULL,
                admin1_code  VARCHAR(20)  NULL,
                PRIMARY KEY (geoname_id)
            )
            SQL);

        $this->addSql('CREATE INDEX idx_geo_geo_place_name_trgm ON geo.geo_place USING GIN (name gin_trgm_ops)');
        $this->addSql('CREATE INDEX idx_geo_geo_place_ascii_name_trgm ON geo.geo_place USING GIN (ascii_name gin_trgm_ops)');

        $this->addSql("COMMENT ON TABLE geo.geo_place IS 'Referential of geographic places imported from the GeoNames open dataset, used to power Geo Place Search (fuzzy autocomplete). Distinct from the free-text city field on a Hotel Address.'");
        $this->addSql("COMMENT ON COLUMN geo.geo_place.geoname_id IS 'GeoNames numeric identifier (stable across dump re-imports) — primary key.'");
        $this->addSql("COMMENT ON COLUMN geo.geo_place.name IS 'GeoNames display name of the place, in its native/local spelling (e.g. \"Paris\").'");
        $this->addSql("COMMENT ON COLUMN geo.geo_place.ascii_name IS 'ASCII-normalized name (accents stripped), used together with name for pg_trgm fuzzy matching.'");
        $this->addSql("COMMENT ON COLUMN geo.geo_place.country_code IS 'ISO 3166-1 alpha-2 country code, as provided by GeoNames.'");
        $this->addSql("COMMENT ON COLUMN geo.geo_place.admin1_code IS 'Raw GeoNames admin1 subdivision code (state/region, e.g. \"TX\"), not resolved to a full name. Nullable: absent for some countries.'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS geo.geo_place');
        $this->addSql('DROP SCHEMA IF EXISTS geo');
    }
}
