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
    case AdminLoginMagicLink = 'admin_login_magic_link';
    case MemberAccessMagicLink = 'member_access_magic_link';
    case MemberMessageSubmittedNotification = 'member_message_submitted_notification';
    case EventHelpRequestConfirmation = 'event_help_request_confirmation';
    case MemberEmailConsentRequest = 'member_email_consent_request';
    case MemberEmailConsentOptOutRegret = 'member_email_consent_opt_out_regret';

    public function label(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => 'Benachrichtigung: neuer Mitgliedsantrag',
            self::MembershipApplicationApproved => 'Bestätigung: Mitgliedsantrag angenommen',
            self::AdminLoginMagicLink => 'Anmeldelink: Redaktion',
            self::MemberAccessMagicLink => 'Zugangslink: Meine Mitgliedschaft',
            self::MemberMessageSubmittedNotification => 'Benachrichtigung: Nachricht von einem Mitglied',
            self::EventHelpRequestConfirmation => 'Bestätigung: Helferanmeldung',
            self::MemberEmailConsentRequest => 'Bestätigungslink: E-Mail-Einwilligung',
            self::MemberEmailConsentOptOutRegret => 'Rückmeldung: E-Mail-Einwilligung widerrufen',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => 'Geht an die unter „Benachrichtigungen" hinterlegten Empfänger, sobald jemand einen Mitgliedsantrag stellt.',
            self::MembershipApplicationApproved => 'Geht an die E-Mail-Adresse der ersten antragstellenden Person, sobald ihr Mitgliedsantrag als Mitglied angelegt (freigegeben) wird.',
            self::AdminLoginMagicLink => 'Geht an die E-Mail-Adresse eines Redaktions-Benutzers, sobald über die Anmeldeseite ein Anmeldelink angefordert wird.',
            self::MemberAccessMagicLink => 'Geht an die eingegebene E-Mail-Adresse, sobald über „Meine Mitgliedschaft" ein Zugang angefordert wird.',
            self::MemberMessageSubmittedNotification => 'Geht an die unter „Benachrichtigungen" hinterlegten Empfänger, sobald über „Meine Mitgliedschaft" eine Nachricht gesendet wird.',
            self::EventHelpRequestConfirmation => 'Geht raus, sobald eine Helferanmeldung ("Ich möchte helfen!") beim Absenden automatisch einem Mitglied zugeordnet werden konnte (nicht beim nachträglichen manuellen Verknüpfen) — an die E-Mail-Adresse des Mitglieds, sonst an dessen Haushalt, sowie zusätzlich an eine im Formular angegebene, abweichende E-Mail-Adresse.',
            self::MemberEmailConsentRequest => 'Geht an die E-Mail-Adresse eines Mitglieds, sobald die Redaktion unter „Mitgliederverwaltung“ → Mitglied → Kontaktdaten die E-Mail-Einwilligung anfordert — der Erhalt von Vereinsinformationen per E-Mail gilt erst als zugestimmt, wenn der enthaltene Link angeklickt wird.',
            self::MemberEmailConsentOptOutRegret => 'Geht an die E-Mail-Adresse eines Mitglieds, sobald es unter „Meine Mitgliedschaft“ die E-Mail-Einwilligung selbst abbestellt — enthält einen Link, um das rückgängig zu machen, falls es ein Versehen war.',
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
            self::AdminLoginMagicLink => ['link', 'gueltig_minuten', 'vereinsname'],
            self::MemberAccessMagicLink => ['link', 'passwort', 'gueltig_minuten', 'vereinsname'],
            self::MemberMessageSubmittedNotification => ['vorname', 'nachname', 'mitgliedsnummer', 'nachricht'],
            self::EventHelpRequestConfirmation => ['vorname', 'nachname', 'veranstaltung', 'datum', 'vereinsname'],
            self::MemberEmailConsentRequest => ['vorname', 'link', 'gueltig_tage', 'vereinsname'],
            self::MemberEmailConsentOptOutRegret => ['vorname', 'link', 'gueltig_tage', 'vereinsname'],
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::MembershipApplicationSubmittedNotification => 'Neuer Mitgliedsantrag eingegangen',
            self::MembershipApplicationApproved => 'Willkommen im {{vereinsname}} – deine Mitgliedschaft ist bestätigt',
            self::AdminLoginMagicLink => 'Dein Anmeldelink für die Redaktion',
            self::MemberAccessMagicLink => 'Dein Zugang zu „Meine Mitgliedschaft"',
            self::MemberMessageSubmittedNotification => 'Neue Nachricht über „Meine Mitgliedschaft"',
            self::EventHelpRequestConfirmation => 'Danke für deine Helferanmeldung – {{veranstaltung}}',
            self::MemberEmailConsentRequest => 'Möchtest du per E-Mail auf dem Laufenden bleiben?',
            self::MemberEmailConsentOptOutRegret => 'Schade, dass du keine Neuigkeiten mehr erhalten möchtest',
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
            self::AdminLoginMagicLink => <<<'TEXT'
                Hallo,

                hier ist dein Anmeldelink für die Redaktion im {{vereinsname}}:

                {{link}}

                Der Link ist {{gueltig_minuten}} Minuten gültig und kann nur einmal verwendet werden. Danach kannst du auf der Anmeldeseite einfach einen neuen Link anfordern.

                Hast du diese E-Mail nicht angefordert, kannst du sie einfach ignorieren.

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
            self::EventHelpRequestConfirmation => <<<'TEXT'
                Hallo {{vorname}},

                schön, dass du uns unterstützen möchtest! Wir freuen uns, dich am {{datum}} bei „{{veranstaltung}}" zu sehen.

                Vielen Dank für deinen Einsatz!

                Viele Grüße
                Dein {{vereinsname}}
                TEXT,
            self::MemberEmailConsentRequest => <<<'TEXT'
                Hallo {{vorname}},

                du möchtest über Neuigkeiten und Informationen rund um den {{vereinsname}} per E-Mail auf dem Laufenden bleiben? Dann bestätige das bitte über den folgenden Link:

                {{link}}

                Der Link ist {{gueltig_tage}} Tage gültig. Erst nach der Bestätigung schicken wir dir Vereinsinformationen per E-Mail.

                Hast du das nicht angefordert, kannst du diese E-Mail einfach ignorieren — es ändert sich dann nichts.

                Viele Grüße
                Dein {{vereinsname}}
                TEXT,
            self::MemberEmailConsentOptOutRegret => <<<'TEXT'
                Hallo {{vorname}},

                schade, dass du keine Neuigkeiten und Informationen des {{vereinsname}} per E-Mail mehr erhalten möchtest — wir haben das soeben umgesetzt.

                War das ein Versehen? Dann klicke einfach hier, um es rückgängig zu machen:

                {{link}}

                Der Link ist {{gueltig_tage}} Tage gültig.

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
                      - Arbeitseinsatz (Rückerstattung nach 5 Gemeinschaftsstunden): 10,00 € pro Jahr
                    Gesamt: 110,00 € pro Jahr
                    TEXT,
                'vereinsname' => AssociationName::CURRENT,
            ],
            self::AdminLoginMagicLink => [
                'link' => 'https://waldbad-borkheide.de/admin?login_token=beispiel-token',
                'gueltig_minuten' => '30',
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
            self::EventHelpRequestConfirmation => [
                'vorname' => 'Erika',
                'nachname' => 'Musterfrau',
                'veranstaltung' => 'Frühjahrsputz',
                'datum' => '13.06.2026',
                'vereinsname' => AssociationName::CURRENT,
            ],
            self::MemberEmailConsentRequest => [
                'vorname' => 'Erika',
                'link' => 'https://waldbad-borkheide.de/e-mail-einwilligung?token=beispiel-token',
                'gueltig_tage' => '7',
                'vereinsname' => AssociationName::CURRENT,
            ],
            self::MemberEmailConsentOptOutRegret => [
                'vorname' => 'Erika',
                'link' => 'https://waldbad-borkheide.de/e-mail-einwilligung?token=beispiel-token',
                'gueltig_tage' => '7',
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
            self::MembershipApplicationSubmittedNotification,
            self::AdminLoginMagicLink,
            self::MemberAccessMagicLink,
            self::MemberMessageSubmittedNotification,
            self::EventHelpRequestConfirmation,
            self::MemberEmailConsentRequest,
            self::MemberEmailConsentOptOutRegret => [],
            self::MembershipApplicationApproved => [
                'beitraege' => '<ul style="margin:0 0 12px;padding-left:20px;">'
                    .'<li style="margin-bottom:8px;font-weight:bold;">Erika Musterfrau: 50,00 € pro Jahr'
                    .'<ul style="margin:4px 0 0;padding-left:20px;"><li style="font-style:italic;font-weight:normal;">Familienbeitrag Erwachsene: 50,00 € pro Jahr</li></ul></li>'
                    .'<li style="margin-bottom:8px;font-weight:bold;">Max Mustermann: 60,00 € pro Jahr'
                    .'<ul style="margin:4px 0 0;padding-left:20px;">'
                    .'<li style="font-style:italic;font-weight:normal;">Familienbeitrag Erwachsene: 50,00 € pro Jahr</li>'
                    .'<li style="font-style:italic;font-weight:normal;">Arbeitseinsatz (Rückerstattung nach 5 Gemeinschaftsstunden): 10,00 € pro Jahr</li></ul></li>'
                    .'</ul><p style="margin:0;font-weight:bold;">Gesamt: 110,00 € pro Jahr</p>',
            ],
        };
    }
}
