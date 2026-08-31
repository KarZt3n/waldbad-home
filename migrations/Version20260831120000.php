<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds an optional default required-helper count to the activity catalog, applied when the activity is assigned to an event.';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('event_activity')->addColumn('default_required_helpers', 'integer', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('event_activity')->dropColumn('default_required_helpers');
    }
}
