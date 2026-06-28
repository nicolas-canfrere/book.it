<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop cross-schema FK constraints; add COMMENT ON for hotel and operator columns';
    }

    public function up(Schema $schema): void
    {
        // Les FK inter-schema créées dans Version20260627000001 sont supprimées.
        // L'intégrité référentielle organization_id est garantie au niveau applicatif,
        // pas au niveau base de données, conformément à la règle d'isolation inter-contexte.
        $this->addSql('ALTER TABLE hotel.hotel    DROP CONSTRAINT IF EXISTS hotel_organization_id_fkey');
        $this->addSql('ALTER TABLE operator.operator DROP CONSTRAINT IF EXISTS operator_organization_id_fkey');

        $this->addSql("COMMENT ON COLUMN hotel.hotel.organization_id             IS 'UUID of the owning organization (no cross-schema FK — enforced at application level)'");
        $this->addSql("COMMENT ON COLUMN operator.operator.organization_id       IS 'UUID of the operator''s organization (no cross-schema FK — enforced at application level)'");
        $this->addSql("COMMENT ON COLUMN operator.operator.role                  IS 'Operator role within the organization: owner | staff'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("COMMENT ON COLUMN operator.operator.role            IS NULL");
        $this->addSql("COMMENT ON COLUMN operator.operator.organization_id IS NULL");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.organization_id       IS NULL");

        $this->addSql('ALTER TABLE operator.operator DROP CONSTRAINT IF EXISTS operator_organization_id_fkey');
        $this->addSql('ALTER TABLE hotel.hotel       DROP CONSTRAINT IF EXISTS hotel_organization_id_fkey');
        $this->addSql('ALTER TABLE operator.operator ADD CONSTRAINT operator_organization_id_fkey FOREIGN KEY (organization_id) REFERENCES organization.organizations(id)');
        $this->addSql('ALTER TABLE hotel.hotel       ADD CONSTRAINT hotel_organization_id_fkey    FOREIGN KEY (organization_id) REFERENCES organization.organizations(id)');
    }
}
