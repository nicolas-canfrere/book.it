<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add address columns and search_key unique constraint to hotel table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE hotel
                ADD COLUMN street_address VARCHAR(255) NOT NULL DEFAULT '',
                ADD COLUMN postal_code VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN city VARCHAR(255) NOT NULL DEFAULT '',
                ADD COLUMN country CHAR(2) NOT NULL DEFAULT '',
                ADD COLUMN search_key VARCHAR(511) NOT NULL DEFAULT ''
        SQL);
        $this->addSql('ALTER TABLE hotel ALTER COLUMN street_address DROP DEFAULT');
        $this->addSql('ALTER TABLE hotel ALTER COLUMN postal_code DROP DEFAULT');
        $this->addSql('ALTER TABLE hotel ALTER COLUMN city DROP DEFAULT');
        $this->addSql('ALTER TABLE hotel ALTER COLUMN country DROP DEFAULT');
        $this->addSql('ALTER TABLE hotel ALTER COLUMN search_key DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX uniq_hotel_search_key ON hotel (search_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_hotel_search_key');
        $this->addSql(<<<'SQL'
            ALTER TABLE hotel
                DROP COLUMN street_address,
                DROP COLUMN postal_code,
                DROP COLUMN city,
                DROP COLUMN country,
                DROP COLUMN search_key
        SQL);
    }
}
