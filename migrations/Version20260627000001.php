<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SaaS foundation: organization schema, organizations table, organization_id on hotel and operator';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS organization');

        $this->addSql('
            CREATE TABLE organization.organizations (
                id            UUID         PRIMARY KEY,
                name          VARCHAR(255) NOT NULL,
                contact_email VARCHAR(255) NOT NULL,
                status        VARCHAR(20)  NOT NULL DEFAULT \'pending\',
                registered_at TIMESTAMPTZ  NOT NULL
            )
        ');

        // Organisation de migration pour les données existantes
        $this->addSql("
            INSERT INTO organization.organizations (id, name, contact_email, status, registered_at)
            VALUES (
                '00000000-0000-0000-0000-000000000001',
                'Default Organization',
                'admin@book.it',
                'active',
                NOW()
            )
        ");

        // Ajouter organization_id sur hotel.hotel (NOT NULL avec valeur par défaut pour la migration atomique)
        $this->addSql("
            ALTER TABLE hotel.hotel
                ADD COLUMN organization_id UUID NOT NULL
                    DEFAULT '00000000-0000-0000-0000-000000000001'
                    REFERENCES organization.organizations(id)
        ");
        $this->addSql('ALTER TABLE hotel.hotel ALTER COLUMN organization_id DROP DEFAULT');

        // Ajouter organization_id et role sur operator.operator
        $this->addSql("
            ALTER TABLE operator.operator
                ADD COLUMN organization_id UUID NOT NULL
                    DEFAULT '00000000-0000-0000-0000-000000000001'
                    REFERENCES organization.organizations(id),
                ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'owner'
        ");
        $this->addSql('ALTER TABLE operator.operator ALTER COLUMN organization_id DROP DEFAULT');
        // Garder DEFAULT 'owner' sur role — nouveau opérateur sans rôle explicite = owner
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operator.operator DROP COLUMN role');
        $this->addSql('ALTER TABLE operator.operator DROP COLUMN organization_id');
        $this->addSql('ALTER TABLE hotel.hotel DROP COLUMN organization_id');
        $this->addSql('DROP TABLE organization.organizations');
        $this->addSql('DROP SCHEMA IF EXISTS organization');
    }
}
