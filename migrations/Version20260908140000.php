<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds rejection tracking columns to membership_application so an application can be '
            .'declined without being turned into a member.';
    }

    public function up(Schema $schema): void
    {
        $application = $schema->getTable('membership_application');
        $application->addColumn('rejected_at', 'datetime_immutable', ['notnull' => false]);
        $application->addColumn('rejection_reason', 'string', ['length' => 500, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $application = $schema->getTable('membership_application');
        $application->dropColumn('rejection_reason');
        $application->dropColumn('rejected_at');
    }
}
