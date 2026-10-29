<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Eine gespeicherte (angepasste) Vorlage „Zugangslink: Meine Mitgliedschaft“ übernimmt den neuen
 * Standardtext nicht. Statt sie zu überschreiben, werden nur zwei Zeilen umgestellt: die Zeile, die
 * allein `{{link}}` enthält, wird zum Button, die Zeile mit `{{passwort}}` wird fett. Vorlagen, die
 * schon einen Button haben, bleiben unverändert.
 */
final class Version20261029160000 extends AbstractMigration
{
    private const string KEY = 'member_access_magic_link';
    private const string BUTTON = '[[Meine Mitgliedschaft öffnen|{{link}}]]';

    public function getDescription(): string
    {
        return 'Shows the link of a stored „Meine Mitgliedschaft“ access mail template as button and its password line in bold.';
    }

    public function up(Schema $schema): void
    {
        $body = $this->storedBody();
        if ($body === null || str_contains($body, '[[')) {
            return;
        }
        $this->updateBody($body, self::withButtonAndBoldPassword($body));
    }

    public function down(Schema $schema): void
    {
        $body = $this->storedBody();
        if ($body === null) {
            return;
        }
        $this->updateBody($body, self::withoutButtonAndBoldPassword($body));
    }

    public static function withButtonAndBoldPassword(string $body): string
    {
        return self::mapLines($body, static function (string $line): string {
            $trimmed = trim($line);
            if ($trimmed === '{{link}}') {
                return str_replace('{{link}}', self::BUTTON, $line);
            }
            if (str_contains($trimmed, '{{passwort}}') && !str_contains($trimmed, '*')) {
                return str_replace($trimmed, '**'.$trimmed.'**', $line);
            }

            return $line;
        });
    }

    public static function withoutButtonAndBoldPassword(string $body): string
    {
        return self::mapLines($body, static function (string $line): string {
            $trimmed = trim($line);
            if ($trimmed === self::BUTTON) {
                return str_replace(self::BUTTON, '{{link}}', $line);
            }
            if (str_contains($trimmed, '{{passwort}}') && preg_match('/^\*\*(.+)\*\*$/', $trimmed, $match) === 1) {
                return str_replace($trimmed, $match[1], $line);
            }

            return $line;
        });
    }

    /** @param callable(string): string $map */
    private static function mapLines(string $body, callable $map): string
    {
        return implode("\n", array_map($map, explode("\n", $body)));
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
