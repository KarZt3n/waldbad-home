<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds hierarchical parent-child relations to CMS pages.';
    }

    public function up(Schema $schema): void
    {
        $page = $schema->getTable('cms_page');
        $page->addColumn('parent_id', 'string', ['length' => 36, 'notnull' => false]);
        $page->addIndex(['parent_id', 'navigation_position'], 'idx_cms_page_parent_position');
    }

    public function down(Schema $schema): void
    {
        $page = $schema->getTable('cms_page');
        $page->dropIndex('idx_cms_page_parent_position');
        $page->dropColumn('parent_id');
    }
}
