<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make operator_organization_id_fkey deferrable to support cross-connection onboarding writes in tests';
    }

    public function up(Schema $schema): void
    {
        // The FK operator.operator(organization_id) → organization.organizations(id) is a cross-schema constraint.
        // During onboarding, both the organization and the operator are created in a single request, using two
        // separate DBAL connections (organization + operator). In test environments, DAMADoctrineTestBundle wraps
        // each connection in its own transaction; without deferral, the FK check fires before the organization
        // insert is visible to the operator connection. Making the constraint DEFERRABLE INITIALLY DEFERRED means
        // the check runs at COMMIT time — which in DAMA tests is never (they roll back), and in production is
        // per-statement in auto-commit mode (equivalent to IMMEDIATE).
        $this->addSql('ALTER TABLE operator.operator ALTER CONSTRAINT operator_organization_id_fkey DEFERRABLE INITIALLY DEFERRED');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operator.operator ALTER CONSTRAINT operator_organization_id_fkey NOT DEFERRABLE');
    }
}
