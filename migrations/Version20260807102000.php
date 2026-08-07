<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seeds a publication snapshot for legacy drafts that were published before being edited.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO cms_page_publication (
                page_id, title, slug, navigation_label, parent_id, blocks, visible, show_in_navigation,
                navigation_position, seo_title, seo_description, page_version, created_at, updated_at, published_at
            )
            SELECT
                source_page.id, source_page.title, source_page.slug, source_page.navigation_label,
                source_page.parent_id, source_page.blocks, source_page.visible, source_page.show_in_navigation,
                source_page.navigation_position, source_page.seo_title, source_page.seo_description,
                source_page.version, source_page.created_at, source_page.updated_at, source_page.published_at
            FROM cms_page source_page
            WHERE source_page.published_at IS NOT NULL
              AND source_page.status <> 'archived'
              AND NOT EXISTS (
                  SELECT 1 FROM cms_page_publication publication WHERE publication.page_id = source_page.id
              )
            SQL);
    }

    public function down(Schema $schema): void
    {
    }
}
