<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260517120940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pricing_promotion table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE pricing_promotion (
                id UUID NOT NULL,
                room_id UUID NOT NULL,
                check_in DATE NOT NULL,
                check_out DATE NOT NULL,
                discount_percent SMALLINT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_pricing_promotion_room_id ON pricing_promotion (room_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_pricing_promotion_room_id');
        $this->addSql('DROP TABLE pricing_promotion');
    }
}
