<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds recorded participation time to event volunteer registrations.';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('event_help_request')->addColumn('participation_minutes', 'integer', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('event_help_request')->dropColumn('participation_minutes');
    }
}
