<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reservation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE reservation (
                id UUID NOT NULL,
                room_id UUID NOT NULL,
                booker_id UUID NOT NULL,
                check_in DATE NOT NULL,
                check_out DATE NOT NULL,
                total_price INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_reservation_room_id ON reservation (room_id)');
        $this->addSql('CREATE INDEX idx_reservation_booker_id ON reservation (booker_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_reservation_booker_id');
        $this->addSql('DROP INDEX idx_reservation_room_id');
        $this->addSql('DROP TABLE reservation');
    }
}
