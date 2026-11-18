<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Gespeicherte (angepasste) Mailvorlagen übernehmen neue Standardtexte nicht. Wie in
 * `Version20261029220000` wird daher nur gezielt angepasst: Eine Zeile, die allein `{{link}}` enthält,
 * wird zum Button (eine bekannte Einleitungszeile davor entfällt), und die Benachrichtigungen der
 * Mitgliederverwaltung bekommen einen Button zum passenden Reiter, falls sie noch keinen Link haben.
 */
final class Version20261118120000 extends AbstractMigration
{
    /** Vorlage => [Button-Beschriftung, bisherige Einleitungszeile vor `{{link}}`]. */
    private const array LINK_LINES = [
        'member_email_consent_request' => ['E-Mail-Einwilligung bestätigen', null],
        'member_email_consent_opt_out_regret' => ['Wieder anmelden', null],
        'event_help_request_submitted_notification' => ['Helfer im Admin-Bereich ansehen', 'Alle Helfer dieser Veranstaltung im Admin-Bereich:'],
        'contact_request_submitted_notification' => ['Anfrage im Admin-Bereich bearbeiten', 'Anfrage im Admin-Bereich bearbeiten:'],
        'guestbook_entry_submitted_notification' => ['Eintrag freigeben oder ablehnen', 'Eintrag im Admin-Bereich freigeben oder ablehnen:'],
    ];

    /** Vorlagen, die jetzt einen `{{link}}` in die Mitgliederverwaltung erhalten. */
    private const array NEW_LINKS = [
        'membership_application_submitted_notification' => 'Mitgliedsanträge öffnen',
        'member_message_submitted_notification' => 'Mitgliedernachrichten öffnen',
    ];

    public function getDescription(): string
    {
        return 'Shows links in stored mail templates as buttons.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::LINK_LINES as $key => [$label, $intro]) {
            $body = $this->storedBody($key);
            if ($body === null) {
                continue;
            }
            $lines = explode("\n", $body);
            foreach ($lines as $index => $line) {
                if (trim($line) !== '{{link}}') {
                    continue;
                }
                $lines[$index] = str_replace('{{link}}', sprintf('[[%s|{{link}}]]', $label), $line);
                if ($intro !== null && $index > 0 && trim($lines[$index - 1]) === $intro) {
                    unset($lines[$index - 1]);
                }
            }
            $this->updateBody($key, $body, implode("\n", $lines));
        }

        foreach (self::NEW_LINKS as $key => $label) {
            $body = $this->storedBody($key);
            if ($body === null || str_contains($body, '{{link}}')) {
                continue;
            }
            $this->updateBody($key, $body, rtrim($body)."\n\n".sprintf('[[%s|{{link}}]]', $label));
        }
    }

    public function down(Schema $schema): void
    {
        // Angepasste Vorlagen lassen sich nicht verlustfrei zurückführen; Buttons stören ältere Versionen nicht.
    }

    private function storedBody(string $key): ?string
    {
        $body = $this->connection->fetchOne('SELECT body FROM mail_template WHERE template_key = ?', [$key]);

        return is_string($body) ? $body : null;
    }

    private function updateBody(string $key, string $before, string $after): void
    {
        if ($after !== $before) {
            $this->addSql('UPDATE mail_template SET body = :body WHERE template_key = :key', ['body' => $after, 'key' => $key]);
        }
    }
}
