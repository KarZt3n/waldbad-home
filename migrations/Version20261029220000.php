<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Eine gespeicherte (angepasste) Vorlage „Mitgliedsantrag angenommen“ übernimmt den neuen Standardtext
 * nicht. Statt sie zu überschreiben, wird nur die Zeile, die allein `{{link}}` enthält, zum Button
 * (Vorbild: `Version20261029160000`). Vorlagen, die schon einen Button haben, bleiben unverändert.
 */
final class Version20261029220000 extends AbstractMigration
{
    private const string KEY = 'membership_application_approved';
    private const string BUTTON = '[[Meine Mitgliedschaft öffnen|{{link}}]]';

    public function getDescription(): string
    {
        return 'Shows the „Meine Mitgliedschaft“ link of a stored approval mail template as button.';
    }

    public function up(Schema $schema): void
    {
        $body = $this->storedBody();
        if ($body === null || str_contains($body, '[[')) {
            return;
        }
        $this->updateBody($body, self::mapLines($body, self::BUTTON, '{{link}}'));
    }

    public function down(Schema $schema): void
    {
        $body = $this->storedBody();
        if ($body === null) {
            return;
        }
        $this->updateBody($body, self::mapLines($body, '{{link}}', self::BUTTON));
    }

    private static function mapLines(string $body, string $replacement, string $line): string
    {
        return implode("\n", array_map(
            static fn (string $current): string => trim($current) === $line ? str_replace($line, $replacement, $current) : $current,
            explode("\n", $body),
        ));
    }

    private function storedBody(): ?string
    {
        $body = $this->connection->fetchOne('SELECT body FROM mail_template WHERE template_key = ?', [self::KEY]);

        return is_string($body) ? $body : null;
    }

    private function updateBody(string $before, string $after): void
    {
        if ($after !== $before) {
            $this->addSql('UPDATE mail_template SET body = :body WHERE template_key = :key', ['body' => $after, 'key' => self::KEY]);
        }
    }
}
