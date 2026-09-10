<?php

namespace App\Logic\Settings\MailTemplate\Model;

use App\Logic\Settings\Email\Model\AssociationName;

/**
 * Registriert jede E-Mail, deren Text redaktionell pflegbar ist, statt fest im Code zu stehen
 * (siehe „Einstellungen" → „E-Mail-Einstellungen" → „Mailvorlagen"). Neue Vorlagen werden hier
 * ergänzt — Oberfläche und Speicherung (`GetMailTemplatesQuery`, Migration) bauen automatisch
 * darauf auf, es ist nur der eigentliche Versand-Aufruf an der auslösenden Stelle nötig (siehe
 * `NotificationMailer`, verwendet z. B. in `SubmitMembershipApplicationUseCase` und
 * `ReleaseMembershipApplicationUseCase`).
 *
 * Platzhalter im Text werden als `{{name}}` geschrieben und beim Versand ersetzt (siehe
 * `MailTemplateRenderer`) — welche für eine Vorlage verfügbar sind, listet `placeholders()`.
 */
enum MailTemplateKey: string
{
    case MembershipApplicationSubmittedNotification = 'membership_application_submitted_notification';
    case MembershipApplicationApproved = 'membership_application_approved';
    case MemberAccessMagicLink = 'member_access_magic_link';
    case MemberMessageSubmittedNotification = 'member_message_submitted_notification';

    public function label(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => 'Benachrichtigung: neuer Mitgliedsantrag',
            self::MembershipApplicationApproved => 'Bestätigung: Mitgliedsantrag angenommen',
            self::MemberAccessMagicLink => 'Zugangslink: Meine Mitgliedschaft',
            self::MemberMessageSubmittedNotification => 'Benachrichtigung: Nachricht von einem Mitglied',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => 'Geht an die unter „Benachrichtigungen" hinterlegten Empfänger, sobald jemand einen Mitgliedsantrag stellt.',
            self::MembershipApplicationApproved => 'Geht an die E-Mail-Adresse der ersten antragstellenden Person, sobald ihr Mitgliedsantrag als Mitglied angelegt (freigegeben) wird.',
            self::MemberAccessMagicLink => 'Geht an die eingegebene E-Mail-Adresse, sobald über „Meine Mitgliedschaft" ein Zugang angefordert wird.',
            self::MemberMessageSubmittedNotification => 'Geht an die unter „Benachrichtigungen" hinterlegten Empfänger, sobald über „Meine Mitgliedschaft" eine Nachricht gesendet wird.',
        };
    }

    /**
     * @return list<string>
     */
    public function placeholders(): array
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => ['vorname', 'nachname', 'mitgliedschaftsart'],
            self::MembershipApplicationApproved => ['vorname', 'nachname', 'mitgliedsnummer', 'beitrittsdatum', 'personen', 'beitraege', 'vereinsname'],
            self::MemberAccessMagicLink => ['link', 'passwort', 'gueltig_minuten', 'vereinsname'],
            self::MemberMessageSubmittedNotification => ['vorname', 'nachname', 'mitgliedsnummer', 'nachricht'],
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => 'Neuer Mitgliedsantrag eingegangen',
            self::MembershipApplicationApproved => 'Willkommen im {{vereinsname}} – deine Mitgliedschaft ist bestätigt',
            self::MemberAccessMagicLink => 'Dein Zugang zu „Meine Mitgliedschaft"',
            self::MemberMessageSubmittedNotification => 'Neue Nachricht über „Meine Mitgliedschaft"',
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => <<<'TEXT'
                {{vorname}} {{nachname}} hat einen Mitgliedsantrag gestellt ({{mitgliedschaftsart}}).

                Bitte im Admin-Bereich unter „Mitgliederverwaltung“ → „Mitgliedsanträge“ prüfen.
                TEXT,
            self::MembershipApplicationApproved => <<<'TEXT'
                Hallo {{vorname}} {{nachname}},

                herzlich willkommen im {{vereinsname}}! Dein Mitgliedsantrag wurde angenommen — du bist ab dem {{beitrittsdatum}} Mitglied (Mitgliedsnummer {{mitgliedsnummer}}).

                Deine Mitgliedskarte(n) können vor Ort im Waldbad abgeholt werden.

                Übersicht der angemeldeten Personen:
                {{personen}}

                Beiträge:

                {{beitraege}}

                Bei Fragen melde dich gerne bei uns.

                Viele Grüße
                Dein {{vereinsname}}
                TEXT,
            self::MemberAccessMagicLink => <<<'TEXT'
                Hallo,

                hier ist dein Zugang zu „Meine Mitgliedschaft" im {{vereinsname}}:

                {{link}}

                Dein Passwort dazu: {{passwort}}

                Der Link ist {{gueltig_minuten}} Minuten gültig. Danach kannst du auf der Seite „Meine Mitgliedschaft" einfach einen neuen Zugang anfordern.

                Hast du diese E-Mail nicht angefordert, kannst du sie einfach ignorieren.

                Viele Grüße
                Dein {{vereinsname}}
                TEXT,
            self::MemberMessageSubmittedNotification => <<<'TEXT'
                {{vorname}} {{nachname}} (Mitgliedsnummer {{mitgliedsnummer}}) hat über „Meine Mitgliedschaft" eine Nachricht gesendet:

                {{nachricht}}

                Bitte im Admin-Bereich unter „Mitgliederverwaltung“ → „Mitgliedernachrichten“ prüfen.
                TEXT,
        };
    }

    /**
     * Beispielhafte Werte für jeden in `placeholders()` gelisteten Platzhalter, mit denen die
     * „Vorschau“-Funktion im Mailvorlagen-Editor arbeitet (siehe `PreviewMailTemplateUseCase`) —
     * ausgedachte, aber realistische Beispieldaten, keine echten Mitgliedsdaten.
     *
     * @return array<string, string>
     */
    public function samplePlaceholders(): array
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => [
                'vorname' => 'Erika',
                'nachname' => 'Musterfrau',
                'mitgliedschaftsart' => 'Familie',
            ],
            self::MembershipApplicationApproved => [
                'vorname' => 'Erika',
                'nachname' => 'Musterfrau',
                'mitgliedsnummer' => 'Bad-01234',
                'beitrittsdatum' => '01.06.2026',
                'personen' => <<<'TEXT'
                    - Erika Musterfrau (Hauptmitglied), geb. 01.01.1985
                    - Max Mustermann (Familienangehöriger), geb. 01.01.1983
                    TEXT,
                'beitraege' => <<<'TEXT'
                    - Erika Musterfrau: 50,00 € pro Jahr
                      - Familienbeitrag Erwachsene: 50,00 € pro Jahr
                    - Max Mustermann: 60,00 € pro Jahr
                      - Familienbeitrag Erwachsene: 50,00 € pro Jahr
                      - Arbeitseinsatz-Zuschlag: 10,00 € pro Jahr
                    Gesamt: 110,00 € pro Jahr
                    TEXT,
                'vereinsname' => AssociationName::CURRENT,
            ],
            self::MemberAccessMagicLink => [
                'link' => 'https://waldbad-borkheide.de/meine-mitgliedschaft?token=beispiel-token',
                'passwort' => 'aB3!xy9?',
                'gueltig_minuten' => '30',
                'vereinsname' => AssociationName::CURRENT,
            ],
            self::MemberMessageSubmittedNotification => [
                'vorname' => 'Erika',
                'nachname' => 'Musterfrau',
                'mitgliedsnummer' => 'Bad-01234',
                'nachricht' => 'Meine neue Telefonnummer lautet 01234 567890.',
            ],
        };
    }

    /**
     * Für Platzhalter mit eigener HTML-Darstellung (siehe `MailTemplateRenderer::render()`,
     * `$htmlBlocks`) das dazu passende Beispiel-HTML für die Vorschau — nur für `beitraege`
     * (verschachtelte Liste, siehe `ReleaseMembershipApplicationUseCase::formatContributionsAsHtml()`),
     * sonst leer.
     *
     * @return array<string, string>
     */
    public function sampleHtmlBlocks(): array
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification,
            self::MemberAccessMagicLink,
            self::MemberMessageSubmittedNotification => [],
            self::MembershipApplicationApproved => [
                'beitraege' => '<ul style="margin:0 0 12px;padding-left:20px;">'
                    .'<li style="margin-bottom:8px;">Erika Musterfrau: 50,00 € pro Jahr'
                    .'<ul style="margin:4px 0 0;padding-left:20px;"><li>Familienbeitrag Erwachsene: 50,00 € pro Jahr</li></ul></li>'
                    .'<li style="margin-bottom:8px;">Max Mustermann: 60,00 € pro Jahr'
                    .'<ul style="margin:4px 0 0;padding-left:20px;">'
                    .'<li>Familienbeitrag Erwachsene: 50,00 € pro Jahr</li>'
                    .'<li>Arbeitseinsatz-Zuschlag: 10,00 € pro Jahr</li></ul></li>'
                    .'</ul><p style="margin:0;font-weight:bold;">Gesamt: 110,00 € pro Jahr</p>',
            ],
        };
    }
}
