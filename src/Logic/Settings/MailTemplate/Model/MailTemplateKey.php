<?php

namespace App\Logic\Settings\MailTemplate\Model;

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
}
