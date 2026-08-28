<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the standalone "Veranstaltung" module (events and work assignments) with its activity and call-to-action detail tables.';
    }

    public function up(Schema $schema): void
    {
        $schedule = $schema->createTable('event_schedule');
        $schedule->addColumn('id', 'string', ['length' => 36]);
        $schedule->addColumn('kind', 'string', ['length' => 20]);
        $schedule->addColumn('title', 'string', ['length' => 180]);
        $schedule->addColumn('date', 'string', ['length' => 10]);
        $schedule->addColumn('time', 'string', ['length' => 5]);
        $schedule->addColumn('content', 'text');
        $schedule->addColumn('media_url', 'string', ['length' => 255, 'notnull' => false]);
        $schedule->addColumn('media_alt', 'string', ['length' => 255, 'notnull' => false]);
        $schedule->addColumn('media_source', 'string', ['length' => 300, 'notnull' => false]);
        $schedule->addColumn('layout', 'string', ['length' => 20, 'notnull' => false]);
        $schedule->addColumn('image_width_percent', 'integer', ['notnull' => false]);
        $schedule->addColumn('vertical_alignment', 'string', ['length' => 20, 'notnull' => false]);
        $schedule->addColumn('text_alignment', 'string', ['length' => 20, 'notnull' => false]);
        $schedule->addColumn('image_fit', 'string', ['length' => 20, 'notnull' => false]);
        $schedule->addColumn('help_enabled', 'boolean');
        $schedule->addColumn('help_button_label', 'string', ['length' => 80, 'notnull' => false]);
        $schedule->addColumn('visible', 'boolean');
        $schedule->addColumn('created_at', 'datetime_immutable');
        $schedule->addColumn('updated_at', 'datetime_immutable');
        $schedule->setPrimaryKey(['id']);
        $schedule->addIndex(['kind', 'date'], 'idx_event_schedule_kind_date');
        $schedule->addIndex(['visible', 'date'], 'idx_event_schedule_visible_date');

        $activity = $schema->createTable('event_schedule_activity');
        $activity->addColumn('id', 'string', ['length' => 36]);
        $activity->addColumn('schedule_id', 'string', ['length' => 36]);
        $activity->addColumn('position', 'integer');
        $activity->addColumn('activity_id', 'string', ['length' => 36]);
        $activity->addColumn('required_helpers', 'integer');
        $activity->addColumn('time', 'string', ['length' => 5, 'notnull' => false]);
        $activity->addColumn('meet_time', 'string', ['length' => 5, 'notnull' => false]);
        $activity->addColumn('meet_place', 'string', ['length' => 160, 'notnull' => false]);
        $activity->addColumn('remark', 'string', ['length' => 500, 'notnull' => false]);
        $activity->setPrimaryKey(['id']);
        $activity->addIndex(['schedule_id', 'position'], 'idx_event_schedule_activity_schedule_position');
        $activity->addForeignKeyConstraint('event_schedule', ['schedule_id'], ['id'], ['onDelete' => 'CASCADE']);

        $callToAction = $schema->createTable('event_schedule_call_to_action');
        $callToAction->addColumn('id', 'string', ['length' => 36]);
        $callToAction->addColumn('schedule_id', 'string', ['length' => 36]);
        $callToAction->addColumn('position', 'integer');
        $callToAction->addColumn('label', 'string', ['length' => 80]);
        $callToAction->addColumn('url', 'string', ['length' => 2048, 'notnull' => false]);
        $callToAction->addColumn('page_id', 'string', ['length' => 36, 'notnull' => false]);
        $callToAction->setPrimaryKey(['id']);
        $callToAction->addIndex(['schedule_id', 'position'], 'idx_event_schedule_cta_schedule_position');
        $callToAction->addForeignKeyConstraint('event_schedule', ['schedule_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('event_schedule_call_to_action');
        $schema->dropTable('event_schedule_activity');
        $schema->dropTable('event_schedule');
    }
}
