<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the membership application extension to the existing membership page.';
    }

    public function up(Schema $schema): void
    {
        $this->changeMembershipPage(true);
    }

    public function down(Schema $schema): void
    {
        $this->changeMembershipPage(false);
    }

    private function changeMembershipPage(bool $add): void
    {
        $page = $this->connection->fetchAssociative(
            'SELECT id, blocks FROM cms_page WHERE slug = :slug',
            ['slug' => 'mitglied-werden'],
        );
        if ($page === false || !is_string($page['id'] ?? null) || !is_string($page['blocks'] ?? null)) {
            return;
        }

        $blocks = json_decode($page['blocks'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new \RuntimeException('Die Inhaltsblöcke der Mitgliedschaftsseite sind ungültig.');
        }
        $hasExtension = false;
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'extension' && ($block['extensionKey'] ?? null) === 'membership_application') {
                $hasExtension = true;
                break;
            }
        }

        if ($add && !$hasExtension) {
            $blocks[] = [
                'type' => 'extension',
                'content' => '',
                'mediaUrl' => null,
                'mediaAlt' => null,
                'linkUrl' => null,
                'linkLabel' => null,
                'layout' => null,
                'imageWidthPercent' => null,
                'verticalAlignment' => null,
                'textAlignment' => null,
                'imageFit' => null,
                'embeddedPageId' => null,
                'eventTitle' => null,
                'eventDate' => null,
                'eventTime' => null,
                'extensionKey' => 'membership_application',
            ];
        } elseif (!$add && $hasExtension) {
            $blocks = array_values(array_filter(
                $blocks,
                static fn (mixed $block): bool => !is_array($block)
                    || ($block['type'] ?? null) !== 'extension'
                    || ($block['extensionKey'] ?? null) !== 'membership_application',
            ));
        } else {
            return;
        }

        $this->addSql(
            'UPDATE cms_page SET blocks = :blocks WHERE id = :id',
            ['blocks' => json_encode($blocks, JSON_THROW_ON_ERROR), 'id' => $page['id']],
        );
    }
}
