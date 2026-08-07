<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enables volunteer registration by default for existing events without an explicit setting.';
    }

    public function up(Schema $schema): void
    {
        $pages = $this->connection->fetchAllAssociative('SELECT id, blocks FROM cms_page');
        foreach ($pages as $page) {
            if (!is_string($page['id'] ?? null) || !is_string($page['blocks'] ?? null)) {
                continue;
            }
            $blocks = json_decode($page['blocks'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($blocks) || !array_is_list($blocks)) {
                continue;
            }
            $changed = false;
            foreach ($blocks as $index => $block) {
                if (!is_array($block) || ($block['type'] ?? null) !== 'event' || array_key_exists('eventHelpEnabled', $block)) {
                    continue;
                }
                $blocks[$index]['eventIdentifier'] = is_string($block['eventIdentifier'] ?? null) && trim($block['eventIdentifier']) !== ''
                    ? $block['eventIdentifier']
                    : 'event-'.substr(hash('sha256', $page['id'].'-'.$index.'-'.($block['eventTitle'] ?? '').'-'.($block['eventDate'] ?? '')), 0, 32);
                $blocks[$index]['eventHelpEnabled'] = true;
                $blocks[$index]['eventHelpButtonLabel'] = 'Ich möchte helfen!';
                $changed = true;
            }
            if ($changed) {
                $this->addSql(
                    'UPDATE cms_page SET blocks = :blocks WHERE id = :id',
                    ['blocks' => json_encode($blocks, JSON_THROW_ON_ERROR), 'id' => $page['id']],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Explizite und automatisch gesetzte Helferanmeldungen können nachträglich nicht sicher unterschieden werden.');
    }
}
