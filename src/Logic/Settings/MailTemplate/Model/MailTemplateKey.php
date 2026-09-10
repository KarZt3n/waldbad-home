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

    public function label(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => 'Benachrichtigung: neuer Mitgliedsantrag',
            self::MembershipApplicationApproved => 'Bestätigung: Mitgliedsantrag angenommen',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => 'Geht an die unter „Benachrichtigungen" hinterlegten Empfänger, sobald jemand einen Mitgliedsantrag stellt.',
            self::MembershipApplicationApproved => 'Geht an die E-Mail-Adresse der ersten antragstellenden Person, sobald ihr Mitgliedsantrag als Mitglied angelegt (freigegeben) wird.',
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
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => 'Neuer Mitgliedsantrag eingegangen',
            self::MembershipApplicationApproved => 'Willkommen im {{vereinsname}} – deine Mitgliedschaft ist bestätigt',
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
            self::MembershipApplicationSubmittedNotification => [],
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
