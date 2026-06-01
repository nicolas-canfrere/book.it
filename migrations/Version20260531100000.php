<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Search context schema and read model tables (search.hotel_room_types, search.room_index, search.unavailable_periods)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS search');

        $this->addSql(<<<'SQL'
            CREATE TABLE search.hotel_room_types (
                room_type_id  UUID        NOT NULL,
                hotel_id      UUID        NOT NULL,
                hotel_name    VARCHAR(255) NOT NULL,
                city          VARCHAR(255) NOT NULL,
                country       VARCHAR(255) NOT NULL,
                star_rating   SMALLINT    NULL,
                hotel_amenities  JSONB    NOT NULL DEFAULT '[]',
                room_type_name   VARCHAR(255) NOT NULL,
                guest_capacity   SMALLINT NOT NULL,
                bed_composition  JSONB    NOT NULL,
                room_amenities   JSONB    NOT NULL DEFAULT '[]',
                base_price_cents INT      NULL,
                PRIMARY KEY (room_type_id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE search.room_index (
                room_id      UUID NOT NULL,
                room_type_id UUID NOT NULL,
                hotel_id     UUID NOT NULL,
                PRIMARY KEY (room_id),
                CONSTRAINT fk_search_room_index_room_type
                    FOREIGN KEY (room_type_id)
                    REFERENCES search.hotel_room_types (room_type_id)
                    ON DELETE CASCADE
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE search.unavailable_periods (
                id           UUID      NOT NULL,
                room_id      UUID      NOT NULL,
                room_type_id UUID      NOT NULL,
                hotel_id     UUID      NOT NULL,
                period       DATERANGE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_search_unavailable_periods_room
                    FOREIGN KEY (room_id)
                    REFERENCES search.room_index (room_id)
                    ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX idx_search_unavailable_periods_period ON search.unavailable_periods USING GiST (period)');
        $this->addSql('CREATE INDEX idx_search_room_index_room_type ON search.room_index (room_type_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS search.unavailable_periods');
        $this->addSql('DROP TABLE IF EXISTS search.room_index');
        $this->addSql('DROP TABLE IF EXISTS search.hotel_room_types');
        $this->addSql('DROP SCHEMA IF EXISTS search');
    }
}
