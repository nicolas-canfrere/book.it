<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create room table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE room (
                id UUID NOT NULL,
                hotel_id UUID NOT NULL,
                number VARCHAR(50) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql("COMMENT ON COLUMN room.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_room_hotel_number ON room (hotel_id, number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE room');
    }
}
