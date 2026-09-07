<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907120200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds release tracking columns to membership_application so a completed application can be turned into members exactly once.';
    }

    public function up(Schema $schema): void
    {
        $application = $schema->getTable('membership_application');
        $application->addColumn('released_at', 'datetime_immutable', ['notnull' => false]);
        $application->addColumn('released_member_ids', 'text', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $application = $schema->getTable('membership_application');
        $application->dropColumn('released_member_ids');
        $application->dropColumn('released_at');
    }
}
