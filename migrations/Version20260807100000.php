<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores the last published snapshot separately from the editable page draft.';
    }

    public function up(Schema $schema): void
    {
        $publication = $schema->createTable('cms_page_publication');
        $publication->addColumn('page_id', 'string', ['length' => 36]);
        $publication->addColumn('title', 'string', ['length' => 180]);
        $publication->addColumn('slug', 'string', ['length' => 180]);
        $publication->addColumn('navigation_label', 'string', ['length' => 180]);
        $publication->addColumn('parent_id', 'string', ['length' => 36, 'notnull' => false]);
        $publication->addColumn('blocks', 'json');
        $publication->addColumn('visible', 'boolean');
        $publication->addColumn('show_in_navigation', 'boolean');
        $publication->addColumn('navigation_position', 'integer');
        $publication->addColumn('seo_title', 'string', ['length' => 180, 'notnull' => false]);
        $publication->addColumn('seo_description', 'text', ['notnull' => false]);
        $publication->addColumn('page_version', 'integer');
        $publication->addColumn('created_at', 'datetime_immutable');
        $publication->addColumn('updated_at', 'datetime_immutable');
        $publication->addColumn('published_at', 'datetime_immutable');
        $publication->setPrimaryKey(['page_id']);
        $publication->addUniqueIndex(['slug'], 'uniq_cms_page_publication_slug');
        $publication->addIndex(
            ['visible', 'show_in_navigation', 'navigation_position'],
            'idx_cms_page_publication_navigation',
        );
        $publication->addForeignKeyConstraint('cms_page', ['page_id'], ['id'], ['onDelete' => 'CASCADE']);

    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('cms_page_publication');
    }
}
