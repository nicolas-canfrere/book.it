<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516070048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create booker table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE booker (
                id UUID NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(50) NOT NULL,
                date_of_birth DATE NOT NULL,
                registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql("COMMENT ON COLUMN booker.registered_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_booker_email ON booker (LOWER(email))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE booker');
    }
}
