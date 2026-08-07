<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds encrypted membership applications and their applicants.';
    }

    public function up(Schema $schema): void
    {
        $application = $schema->createTable('membership_application');
        $application->addColumn('id', 'string', ['length' => 36]);
        $application->addColumn('membership_type', 'string', ['length' => 20]);
        $application->addColumn('account_holder', 'string', ['length' => 180]);
        $application->addColumn('iban_encrypted', 'text');
        $application->addColumn('bank_name', 'string', ['length' => 180, 'notnull' => false]);
        $application->addColumn('signer_name', 'string', ['length' => 180]);
        $application->addColumn('email_consent', 'boolean');
        $application->addColumn('declaration_version', 'string', ['length' => 40]);
        $application->addColumn('status', 'string', ['length' => 20]);
        $application->addColumn('external_reference', 'string', ['length' => 180, 'notnull' => false]);
        $application->addColumn('failure_reason', 'string', ['length' => 500, 'notnull' => false]);
        $application->addColumn('version', 'integer', ['default' => 1]);
        $application->addColumn('submitted_at', 'datetime_immutable');
        $application->addColumn('updated_at', 'datetime_immutable');
        $application->addColumn('processing_at', 'datetime_immutable', ['notnull' => false]);
        $application->addColumn('completed_at', 'datetime_immutable', ['notnull' => false]);
        $application->setPrimaryKey(['id']);
        $application->addIndex(['status', 'submitted_at'], 'idx_membership_status_submitted');
        $application->addUniqueIndex(['external_reference'], 'uniq_membership_external_reference');

        $applicant = $schema->createTable('membership_applicant');
        $applicant->addColumn('id', 'string', ['length' => 36]);
        $applicant->addColumn('application_id', 'string', ['length' => 36]);
        $applicant->addColumn('position', 'integer');
        $applicant->addColumn('first_name', 'string', ['length' => 120]);
        $applicant->addColumn('last_name', 'string', ['length' => 120]);
        $applicant->addColumn('birth_date', 'date_immutable');
        $applicant->addColumn('street', 'string', ['length' => 180]);
        $applicant->addColumn('house_number', 'string', ['length' => 20]);
        $applicant->addColumn('postal_code', 'string', ['length' => 5]);
        $applicant->addColumn('city', 'string', ['length' => 180]);
        $applicant->addColumn('phone', 'string', ['length' => 60, 'notnull' => false]);
        $applicant->addColumn('email', 'string', ['length' => 180, 'notnull' => false]);
        $applicant->setPrimaryKey(['id']);
        $applicant->addIndex(['application_id', 'position'], 'idx_membership_applicant_application_position');
        $applicant->addForeignKeyConstraint('membership_application', ['application_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('membership_applicant');
        $schema->dropTable('membership_application');
    }
}
