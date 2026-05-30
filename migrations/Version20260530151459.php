<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530151459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add actual_departure_date column to reservation table for check-out audit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD actual_departure_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP COLUMN actual_departure_date');
    }
}
