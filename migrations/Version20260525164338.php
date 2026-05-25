<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260525164338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create room_type table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE room_type (
            id UUID NOT NULL,
            hotel_id UUID NOT NULL,
            name VARCHAR(100) NOT NULL,
            living_space_count SMALLINT NOT NULL,
            surface_m2 SMALLINT DEFAULT NULL,
            guest_capacity SMALLINT NOT NULL,
            is_accessible BOOLEAN NOT NULL DEFAULT FALSE,
            bed_composition JSONB NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            CONSTRAINT pk_room_type PRIMARY KEY (id),
            CONSTRAINT uq_room_type_hotel_name UNIQUE (hotel_id, name)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE room_type');
    }
}
