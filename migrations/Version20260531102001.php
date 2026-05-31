<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531102001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move pricing tables to pricing schema, rename to drop context prefix';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS pricing');
        $this->addSql('ALTER TABLE pricing_base_rate SET SCHEMA pricing');
        $this->addSql('ALTER TABLE pricing.pricing_base_rate RENAME TO base_rate');
        $this->addSql('ALTER TABLE pricing_cancellation_policy SET SCHEMA pricing');
        $this->addSql('ALTER TABLE pricing.pricing_cancellation_policy RENAME TO cancellation_policy');
        $this->addSql('ALTER TABLE pricing_promotion SET SCHEMA pricing');
        $this->addSql('ALTER TABLE pricing.pricing_promotion RENAME TO promotion');
        $this->addSql('ALTER TABLE pricing_rate_period SET SCHEMA pricing');
        $this->addSql('ALTER TABLE pricing.pricing_rate_period RENAME TO rate_period');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pricing.base_rate RENAME TO pricing_base_rate');
        $this->addSql('ALTER TABLE pricing.pricing_base_rate SET SCHEMA public');
        $this->addSql('ALTER TABLE pricing.cancellation_policy RENAME TO pricing_cancellation_policy');
        $this->addSql('ALTER TABLE pricing.pricing_cancellation_policy SET SCHEMA public');
        $this->addSql('ALTER TABLE pricing.promotion RENAME TO pricing_promotion');
        $this->addSql('ALTER TABLE pricing.pricing_promotion SET SCHEMA public');
        $this->addSql('ALTER TABLE pricing.rate_period RENAME TO pricing_rate_period');
        $this->addSql('ALTER TABLE pricing.pricing_rate_period SET SCHEMA public');
        $this->addSql('DROP SCHEMA IF EXISTS pricing');
    }
}
