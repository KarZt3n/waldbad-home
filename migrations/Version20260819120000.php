<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds reusable event activities and volunteer activity selections.';
    }

    public function up(Schema $schema): void
    {
        $activity = $schema->createTable('event_activity');
        $activity->addColumn('id', 'string', ['length' => 36]);
        $activity->addColumn('name', 'string', ['length' => 120]);
        $activity->addColumn('description', 'text');
        $activity->addColumn('active', 'boolean');
        $activity->addColumn('created_at', 'datetime_immutable');
        $activity->addColumn('updated_at', 'datetime_immutable');
        $activity->setPrimaryKey(['id']);
        $activity->addIndex(['active', 'name'], 'idx_event_activity_active_name');

        $selection = $schema->createTable('event_help_request_activity');
        $selection->addColumn('id', 'string', ['length' => 36]);
        $selection->addColumn('request_id', 'string', ['length' => 36]);
        $selection->addColumn('activity_id', 'string', ['length' => 36]);
        $selection->addColumn('activity_name', 'string', ['length' => 120]);
        $selection->setPrimaryKey(['id']);
        $selection->addUniqueIndex(['request_id', 'activity_id'], 'uniq_event_help_request_activity');
        $selection->addForeignKeyConstraint('event_help_request', ['request_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('event_help_request_activity');
        $schema->dropTable('event_activity');
    }
}
