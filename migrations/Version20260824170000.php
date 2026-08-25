<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824170000 extends AbstractMigration
{
    private const HOME_PAGE_ID = '2bf09f32-13cf-4bd3-9ff4-34473aa2db8a';
    private const OFFER_PAGE_ID = '1c79c834-fc4d-4f7d-9ac0-2c45a9e96542';
    private const OFFER_PAGE_SLUG = 'startseite/wir-bieten-unseren-besuchern';

    public function getDescription(): string
    {
        return 'Moves the feature collection from the home page to an embedded child page.';
    }

    public function up(Schema $schema): void
    {
        $homePage = $this->connection->fetchAssociative(
            'SELECT blocks, version FROM cms_page WHERE id = :id',
            ['id' => self::HOME_PAGE_ID],
        );
        $homePublication = $this->connection->fetchAssociative(
            'SELECT blocks, page_version FROM cms_page_publication WHERE page_id = :id',
            ['id' => self::HOME_PAGE_ID],
        );
        if ($homePage === false || $homePublication === false) {
            throw new \RuntimeException('Die veröffentlichte Startseite wurde nicht gefunden.');
        }
        $existingPageCount = $this->connection->fetchOne('SELECT COUNT(*) FROM cms_page WHERE id = :id OR slug = :slug', [
            'id' => self::OFFER_PAGE_ID,
            'slug' => self::OFFER_PAGE_SLUG,
        ]);
        if ((!is_int($existingPageCount) && !is_string($existingPageCount)) || !is_numeric($existingPageCount)) {
            throw new \RuntimeException('Die vorhandenen Zielseiten konnten nicht geprüft werden.');
        }
        if ((int) $existingPageCount !== 0) {
            throw new \RuntimeException('Die Zielseite „Wir bieten unseren Besuchern“ ist bereits vorhanden.');
        }

        [$draftCollection, $draftBlocks, $draftPosition] = $this->extractCollection($this->requiredString($homePage, 'blocks'));
        [$publishedCollection, $publishedBlocks, $publishedPosition] = $this->extractCollection($this->requiredString($homePublication, 'blocks'));
        array_splice($draftBlocks, $draftPosition, 0, [$this->embeddedPageBlock()]);
        array_splice($publishedBlocks, $publishedPosition, 0, [$this->embeddedPageBlock()]);

        $now = new \DateTimeImmutable();
        $timestamp = $now->format('Y-m-d H:i:s');
        $draftCollectionJson = $this->encodeBlocks([$draftCollection]);
        $publishedCollectionJson = $this->encodeBlocks([$publishedCollection]);

        $this->addSql(<<<'SQL'
            INSERT INTO cms_page (
                id, title, slug, navigation_label, parent_id, blocks, status, visible, show_in_navigation,
                navigation_position, seo_title, seo_description, version, created_at, updated_at, published_at
            ) VALUES (
                :id, :title, :slug, :navigationLabel, :parentId, :blocks, 'published', 1, 0,
                1, :seoTitle, :seoDescription, 1, :createdAt, :updatedAt, :publishedAt
            )
            SQL, [
            'id' => self::OFFER_PAGE_ID,
            'title' => 'Wir bieten unseren Besuchern',
            'slug' => self::OFFER_PAGE_SLUG,
            'navigationLabel' => 'Wir bieten unseren Besuchern',
            'parentId' => self::HOME_PAGE_ID,
            'blocks' => $draftCollectionJson,
            'seoTitle' => 'Wir bieten unseren Besuchern | Waldbad Borkheide',
            'seoDescription' => 'Ausstattung und Angebote des Waldbads Borkheide im Überblick.',
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
            'publishedAt' => $timestamp,
        ]);

        $this->addSql(<<<'SQL'
            INSERT INTO cms_page_publication (
                page_id, title, slug, navigation_label, parent_id, blocks, visible, show_in_navigation,
                navigation_position, seo_title, seo_description, page_version, created_at, updated_at, published_at
            ) VALUES (
                :id, :title, :slug, :navigationLabel, :parentId, :blocks, 1, 0,
                1, :seoTitle, :seoDescription, 1, :createdAt, :updatedAt, :publishedAt
            )
            SQL, [
            'id' => self::OFFER_PAGE_ID,
            'title' => 'Wir bieten unseren Besuchern',
            'slug' => self::OFFER_PAGE_SLUG,
            'navigationLabel' => 'Wir bieten unseren Besuchern',
            'parentId' => self::HOME_PAGE_ID,
            'blocks' => $publishedCollectionJson,
            'seoTitle' => 'Wir bieten unseren Besuchern | Waldbad Borkheide',
            'seoDescription' => 'Ausstattung und Angebote des Waldbads Borkheide im Überblick.',
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
            'publishedAt' => $timestamp,
        ]);

        $this->addSql(
            'UPDATE cms_page SET blocks = :blocks, version = version + 1, updated_at = :updatedAt, published_at = :publishedAt WHERE id = :id',
            [
                'blocks' => $this->encodeBlocks($draftBlocks),
                'updatedAt' => $timestamp,
                'publishedAt' => $timestamp,
                'id' => self::HOME_PAGE_ID,
            ],
        );
        $this->addSql(
            'UPDATE cms_page_publication SET blocks = :blocks, page_version = page_version + 1, updated_at = :updatedAt, published_at = :publishedAt WHERE page_id = :id',
            [
                'blocks' => $this->encodeBlocks($publishedBlocks),
                'updatedAt' => $timestamp,
                'publishedAt' => $timestamp,
                'id' => self::HOME_PAGE_ID,
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $homePage = $this->connection->fetchAssociative('SELECT blocks FROM cms_page WHERE id = :id', ['id' => self::HOME_PAGE_ID]);
        $homePublication = $this->connection->fetchAssociative('SELECT blocks FROM cms_page_publication WHERE page_id = :id', ['id' => self::HOME_PAGE_ID]);
        $offerPage = $this->connection->fetchAssociative('SELECT blocks FROM cms_page WHERE id = :id', ['id' => self::OFFER_PAGE_ID]);
        $offerPublication = $this->connection->fetchAssociative('SELECT blocks FROM cms_page_publication WHERE page_id = :id', ['id' => self::OFFER_PAGE_ID]);
        if ($homePage === false || $homePublication === false || $offerPage === false || $offerPublication === false) {
            throw new \RuntimeException('Die Seitenstruktur für das Zurücksetzen ist unvollständig.');
        }

        $draftCollection = $this->singleCollection($this->requiredString($offerPage, 'blocks'));
        $publishedCollection = $this->singleCollection($this->requiredString($offerPublication, 'blocks'));
        $draftBlocks = $this->replaceEmbedding($this->requiredString($homePage, 'blocks'), $draftCollection);
        $publishedBlocks = $this->replaceEmbedding($this->requiredString($homePublication, 'blocks'), $publishedCollection);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql(
            'UPDATE cms_page SET blocks = :blocks, version = version + 1, updated_at = :updatedAt WHERE id = :id',
            ['blocks' => $this->encodeBlocks($draftBlocks), 'updatedAt' => $now, 'id' => self::HOME_PAGE_ID],
        );
        $this->addSql(
            'UPDATE cms_page_publication SET blocks = :blocks, page_version = page_version + 1, updated_at = :updatedAt WHERE page_id = :id',
            ['blocks' => $this->encodeBlocks($publishedBlocks), 'updatedAt' => $now, 'id' => self::HOME_PAGE_ID],
        );
        $this->addSql('DELETE FROM cms_page_publication WHERE page_id = :id', ['id' => self::OFFER_PAGE_ID]);
        $this->addSql('DELETE FROM cms_page WHERE id = :id', ['id' => self::OFFER_PAGE_ID]);
    }

    /**
     * @return array{array<string, mixed>, list<array<string, mixed>>, int}
     */
    private function extractCollection(string $json): array
    {
        $blocks = $this->decodeBlocks($json);
        foreach ($blocks as $index => $block) {
            if (($block['type'] ?? null) !== 'feature_collection') {
                continue;
            }

            array_splice($blocks, $index, 1);

            return [$block, $blocks, $index];
        }

        throw new \RuntimeException('Auf der Startseite wurde keine Bild-Text-Collection gefunden.');
    }

    /** @return array<string, mixed> */
    private function singleCollection(string $json): array
    {
        $blocks = $this->decodeBlocks($json);
        $block = $blocks[0] ?? null;
        if (!is_array($block) || ($block['type'] ?? null) !== 'feature_collection') {
            throw new \RuntimeException('Die Unterseite enthält nicht die erwartete Bild-Text-Collection.');
        }

        return $block;
    }

    /**
     * @param array<string, mixed> $collection
     * @return list<array<string, mixed>>
     */
    private function replaceEmbedding(string $json, array $collection): array
    {
        $blocks = $this->decodeBlocks($json);
        foreach ($blocks as $index => $block) {
            if (($block['type'] ?? null) === 'embedded_page' && ($block['embeddedPageId'] ?? null) === self::OFFER_PAGE_ID) {
                array_splice($blocks, $index, 1, [$collection]);

                return $blocks;
            }
        }

        throw new \RuntimeException('Die Einbettung der Angebotsseite wurde nicht gefunden.');
    }

    /** @return list<array<string, mixed>> */
    private function decodeBlocks(string $json): array
    {
        $blocks = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new \RuntimeException('Die Inhaltsblöcke besitzen ein ungültiges Format.');
        }
        $normalizedBlocks = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                throw new \RuntimeException('Ein Inhaltsblock besitzt ein ungültiges Format.');
            }
            $normalizedBlock = [];
            foreach ($block as $key => $value) {
                if (!is_string($key)) {
                    throw new \RuntimeException('Ein Inhaltsblock besitzt einen ungültigen Feldnamen.');
                }
                $normalizedBlock[$key] = $value;
            }
            $normalizedBlocks[] = $normalizedBlock;
        }

        return $normalizedBlocks;
    }

    /** @param list<array<string, mixed>> $blocks */
    private function encodeBlocks(array $blocks): string
    {
        return json_encode($blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $row */
    private function requiredString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException(sprintf('Das Feld %s konnte nicht gelesen werden.', $key));
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function embeddedPageBlock(): array
    {
        return [
            'type' => 'embedded_page',
            'content' => '',
            'mediaUrl' => null,
            'mediaAlt' => null,
            'mediaSource' => null,
            'linkUrl' => null,
            'linkLabel' => null,
            'layout' => null,
            'imageWidthPercent' => null,
            'verticalAlignment' => null,
            'textAlignment' => null,
            'imageFit' => null,
            'embeddedPageId' => self::OFFER_PAGE_ID,
            'eventTitle' => null,
            'eventDate' => null,
            'eventTime' => null,
            'eventIdentifier' => null,
            'eventHelpEnabled' => false,
            'eventHelpButtonLabel' => null,
            'eventActivities' => [],
            'extensionKey' => null,
            'collectionColumns' => null,
            'collectionItems' => [],
        ];
    }
}
