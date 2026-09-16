<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds event_template (+ activity/call-to-action detail tables) for reusable '
            .'Veranstaltung/Arbeitseinsatz templates, and an index on '
            .'event_schedule_activity.activity_id used by the new cascade-delete-by-activity cleanup.';
    }

    public function up(Schema $schema): void
    {
        $template = $schema->createTable('event_template');
        $template->addColumn('id', 'string', ['length' => 36]);
        $template->addColumn('kind', 'string', ['length' => 20]);
        $template->addColumn('title', 'string', ['length' => 180]);
        $template->addColumn('content', 'text');
        $template->addColumn('media_url', 'string', ['length' => 255, 'notnull' => false]);
        $template->addColumn('media_alt', 'string', ['length' => 255, 'notnull' => false]);
        $template->addColumn('media_source', 'string', ['length' => 300, 'notnull' => false]);
        $template->addColumn('layout', 'string', ['length' => 20, 'notnull' => false]);
        $template->addColumn('image_width_percent', 'integer', ['notnull' => false]);
        $template->addColumn('vertical_alignment', 'string', ['length' => 20, 'notnull' => false]);
        $template->addColumn('text_alignment', 'string', ['length' => 20, 'notnull' => false]);
        $template->addColumn('image_fit', 'string', ['length' => 20, 'notnull' => false]);
        $template->addColumn('help_enabled', 'boolean');
        $template->addColumn('help_button_label', 'string', ['length' => 80, 'notnull' => false]);
        $template->addColumn('created_at', 'datetime_immutable');
        $template->addColumn('updated_at', 'datetime_immutable');
        $template->setPrimaryKey(['id']);
        $template->addIndex(['kind', 'title'], 'idx_event_template_kind_title');

        $activity = $schema->createTable('event_template_activity');
        $activity->addColumn('id', 'string', ['length' => 36]);
        $activity->addColumn('template_id', 'string', ['length' => 36]);
        $activity->addColumn('position', 'integer');
        $activity->addColumn('activity_id', 'string', ['length' => 36]);
        $activity->addColumn('required_helpers', 'integer');
        $activity->addColumn('time', 'string', ['length' => 5, 'notnull' => false]);
        $activity->addColumn('meet_time', 'string', ['length' => 5, 'notnull' => false]);
        $activity->addColumn('meet_place', 'string', ['length' => 160, 'notnull' => false]);
        $activity->addColumn('remark', 'string', ['length' => 500, 'notnull' => false]);
        $activity->setPrimaryKey(['id']);
        $activity->addIndex(['template_id', 'position'], 'idx_event_template_activity_template_position');
        $activity->addIndex(['activity_id'], 'idx_event_template_activity_activity');
        $activity->addForeignKeyConstraint('event_template', ['template_id'], ['id'], ['onDelete' => 'CASCADE']);

        $callToAction = $schema->createTable('event_template_call_to_action');
        $callToAction->addColumn('id', 'string', ['length' => 36]);
        $callToAction->addColumn('template_id', 'string', ['length' => 36]);
        $callToAction->addColumn('position', 'integer');
        $callToAction->addColumn('label', 'string', ['length' => 80]);
        $callToAction->addColumn('url', 'string', ['length' => 2048, 'notnull' => false]);
        $callToAction->addColumn('page_id', 'string', ['length' => 36, 'notnull' => false]);
        $callToAction->setPrimaryKey(['id']);
        $callToAction->addIndex(['template_id', 'position'], 'idx_event_template_cta_template_position');
        $callToAction->addForeignKeyConstraint('event_template', ['template_id'], ['id'], ['onDelete' => 'CASCADE']);

        $schema->getTable('event_schedule_activity')->addIndex(['activity_id'], 'idx_event_schedule_activity_activity');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('event_schedule_activity')->dropIndex('idx_event_schedule_activity_activity');

        $schema->dropTable('event_template_call_to_action');
        $schema->dropTable('event_template_activity');
        $schema->dropTable('event_template');
    }
}
