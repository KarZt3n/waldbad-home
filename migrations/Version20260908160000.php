<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Removes the never-used external-system transfer workflow from membership_application '
            .'(status, external_reference, failure_reason, processing_at, completed_at) and the '
            .'integration API it fed. An application is now either released into member(s) or '
            .'rejected, nothing else.';
    }

    public function up(Schema $schema): void
    {
        $application = $schema->getTable('membership_application');
        $application->dropIndex('idx_membership_status_submitted');
        $application->dropIndex('uniq_membership_external_reference');
        $application->dropColumn('status');
        $application->dropColumn('external_reference');
        $application->dropColumn('failure_reason');
        $application->dropColumn('processing_at');
        $application->dropColumn('completed_at');
        $application->addIndex(['submitted_at'], 'idx_membership_submitted_at');
    }

    public function down(Schema $schema): void
    {
        $application = $schema->getTable('membership_application');
        $application->dropIndex('idx_membership_submitted_at');
        $application->addColumn('status', 'string', ['length' => 20, 'default' => 'pending']);
        $application->addColumn('external_reference', 'string', ['length' => 180, 'notnull' => false]);
        $application->addColumn('failure_reason', 'string', ['length' => 500, 'notnull' => false]);
        $application->addColumn('processing_at', 'datetime_immutable', ['notnull' => false]);
        $application->addColumn('completed_at', 'datetime_immutable', ['notnull' => false]);
        $application->addIndex(['status', 'submitted_at'], 'idx_membership_status_submitted');
        $application->addUniqueIndex(['external_reference'], 'uniq_membership_external_reference');
    }
}
