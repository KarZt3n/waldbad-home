<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds start and end times to recorded event volunteer participation.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('event_help_request');
        $table->addColumn('participation_from_time', 'string', ['length' => 5, 'notnull' => false]);
        $table->addColumn('participation_to_time', 'string', ['length' => 5, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('event_help_request');
        $table->dropColumn('participation_from_time');
        $table->dropColumn('participation_to_time');
    }
}
