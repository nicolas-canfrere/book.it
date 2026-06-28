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

        $this->addSql("COMMENT ON TABLE  organization.organizations              IS 'Registered hotel organizations (tenants)'");
        $this->addSql("COMMENT ON COLUMN organization.organizations.id           IS 'UUID v4 — internal organization identifier'");
        $this->addSql("COMMENT ON COLUMN organization.organizations.name         IS 'Display name of the organization'");
        $this->addSql("COMMENT ON COLUMN organization.organizations.contact_email IS 'Primary contact email; also used as the owner operator email at onboarding'");
        $this->addSql("COMMENT ON COLUMN organization.organizations.status       IS 'Lifecycle status: pending | active | suspended'");
        $this->addSql("COMMENT ON COLUMN organization.organizations.registered_at IS 'UTC timestamp of the initial registration request'");

        // Données de migration pour les enregistrements existants
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
        // Pas de FK inter-schema : l'intégrité référentielle est garantie au niveau applicatif.
        $this->addSql("
            ALTER TABLE hotel.hotel
                ADD COLUMN organization_id UUID NOT NULL
                    DEFAULT '00000000-0000-0000-0000-000000000001'
        ");
        $this->addSql('ALTER TABLE hotel.hotel ALTER COLUMN organization_id DROP DEFAULT');
        $this->addSql("COMMENT ON COLUMN hotel.hotel.organization_id IS 'UUID of the owning organization (no cross-schema FK — enforced at application level)'");

        // Ajouter organization_id et role sur operator.operator
        // Pas de FK inter-schema : l'intégrité référentielle est garantie au niveau applicatif.
        $this->addSql("
            ALTER TABLE operator.operator
                ADD COLUMN organization_id UUID NOT NULL
                    DEFAULT '00000000-0000-0000-0000-000000000001',
                ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'owner'
        ");
        $this->addSql('ALTER TABLE operator.operator ALTER COLUMN organization_id DROP DEFAULT');
        $this->addSql("COMMENT ON COLUMN operator.operator.organization_id IS 'UUID of the operator''s organization (no cross-schema FK — enforced at application level)'");
        $this->addSql("COMMENT ON COLUMN operator.operator.role            IS 'Operator role within the organization: owner | staff'");
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
