<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligns existing page slugs with the hierarchical navigation structure.';
    }

    public function up(Schema $schema): void
    {
        $pages = $this->loadPages();
        $resolved = [];
        $visiting = [];

        foreach (array_keys($pages) as $id) {
            $this->resolveSlug($id, $pages, $resolved, $visiting);
        }

        if (count($resolved) !== count(array_unique($resolved))) {
            throw new \RuntimeException('Die hierarchischen Seitenslugs sind nicht eindeutig.');
        }

        foreach ($resolved as $id => $slug) {
            if (strlen($slug) > 180) {
                throw new \RuntimeException(sprintf('Der hierarchische Slug der Seite %s überschreitet 180 Zeichen.', $id));
            }

            $temporarySlug = 'slug-migration-'.$id;
            $this->connection->executeStatement(
                'UPDATE cms_page SET slug = :slug WHERE id = :id',
                ['slug' => $temporarySlug, 'id' => $id],
            );
            $this->connection->executeStatement(
                'UPDATE cms_page_publication SET slug = :slug WHERE page_id = :id',
                ['slug' => $temporarySlug, 'id' => $id],
            );
        }

        foreach ($resolved as $id => $slug) {
            $this->connection->executeStatement(
                'UPDATE cms_page SET slug = :slug WHERE id = :id',
                ['slug' => $slug, 'id' => $id],
            );
            $this->connection->executeStatement(
                'UPDATE cms_page_publication SET slug = :slug WHERE page_id = :id',
                ['slug' => $slug, 'id' => $id],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Die vorherigen individuellen Seitenslugs lassen sich nicht zuverlässig rekonstruieren.');
    }

    /**
     * @return array<string, array{id: string, title: string, slug: string, parentId: string|null}>
     */
    private function loadPages(): array
    {
        $pages = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, title, slug, parent_id FROM cms_page') as $row) {
            $id = $row['id'] ?? null;
            $title = $row['title'] ?? null;
            $slug = $row['slug'] ?? null;
            $parentId = $row['parent_id'] ?? null;
            if (!is_string($id) || !is_string($title) || !is_string($slug) || ($parentId !== null && !is_string($parentId))) {
                throw new \RuntimeException('Die Seitendaten konnten nicht vollständig gelesen werden.');
            }

            $pages[$id] = ['id' => $id, 'title' => $title, 'slug' => $slug, 'parentId' => $parentId];
        }

        return $pages;
    }

    /**
     * @param array<string, array{id: string, title: string, slug: string, parentId: string|null}> $pages
     * @param array<string, string> $resolved
     * @param array<string, true> $visiting
     */
    private function resolveSlug(string $id, array $pages, array &$resolved, array &$visiting): string
    {
        if (isset($resolved[$id])) {
            return $resolved[$id];
        }
        if (isset($visiting[$id])) {
            throw new \RuntimeException('Die Seitenstruktur enthält einen Kreislauf.');
        }

        $page = $pages[$id] ?? throw new \RuntimeException(sprintf('Die Seite %s wurde nicht gefunden.', $id));
        $visiting[$id] = true;
        if ($page['parentId'] === null) {
            $slug = $page['slug'];
        } else {
            if (!isset($pages[$page['parentId']])) {
                throw new \RuntimeException(sprintf('Die Elternseite %s wurde nicht gefunden.', $page['parentId']));
            }
            $slug = $this->resolveSlug($page['parentId'], $pages, $resolved, $visiting).'/'.$this->slugify($page['title']);
        }
        unset($visiting[$id]);

        return $resolved[$id] = $slug;
    }

    private function slugify(string $title): string
    {
        $normalized = strtr(mb_strtolower(trim($title), 'UTF-8'), [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
        ]);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($ascii !== false) {
            $normalized = $ascii;
        }
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($normalized));
        if ($slug === null || trim($slug, '-') === '') {
            throw new \RuntimeException(sprintf('Aus dem Seitentitel „%s“ konnte kein Slug erzeugt werden.', $title));
        }

        return trim($slug, '-');
    }
}
