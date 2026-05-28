<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528191728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reservation_guest table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reservation_guest (
            id UUID NOT NULL,
            reservation_id UUID NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            date_of_birth DATE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_reservation_guest_reservation FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_reservation_guest_reservation_id ON reservation_guest (reservation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reservation_guest');
    }
}
