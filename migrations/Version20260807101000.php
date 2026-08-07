<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates publication snapshots for pages that are already published.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO cms_page_publication (
                page_id, title, slug, navigation_label, parent_id, blocks, visible, show_in_navigation,
                navigation_position, seo_title, seo_description, page_version, created_at, updated_at, published_at
            )
            SELECT
                id, title, slug, navigation_label, parent_id, blocks, visible, show_in_navigation,
                navigation_position, seo_title, seo_description, version, created_at, updated_at,
                COALESCE(published_at, updated_at)
            FROM cms_page
            WHERE status = 'published'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM cms_page_publication');
    }
}
