<?php

namespace App\Logic\Content\Extension\Model;

/**
 * Die Seitenerweiterungen, die als Block (`ContentBlockType::Extension`) auf einer Seite eingebunden
 * werden können. Erweiterungen mit Textfeldern (siehe `textFields()`) sind unter „Seiten“ →
 * „Erweiterungen“ redaktionell anpassbar; die Standardtexte stehen hier im Code, gespeichert werden
 * nur Abweichungen (siehe `PageExtensionTexts`).
 */
enum PageExtension: string
{
    case MembershipApplication = 'membership_application';
    case EventsCurrentYear = 'events_current_year';
    case EventsFromCurrentYear = 'events_from_current_year';
    case WorkAssignmentsCurrentYear = 'work_assignments_current_year';
    case NextEvent = 'next_event';
    case NextWorkAssignment = 'next_work_assignment';
    case NextEventOrWorkAssignment = 'next_event_or_work_assignment';
    case Sauna = 'sauna';
    case PhotoAlbums = 'photo_albums';

    public function label(): string
    {
        return match ($this) {
            self::MembershipApplication => 'Mitgliedsantrag',
            self::EventsCurrentYear => 'Veranstaltungen: aktuelles Jahr',
            self::EventsFromCurrentYear => 'Veranstaltung: alle Veranstaltungen ab aktuelles Jahr',
            self::WorkAssignmentsCurrentYear => 'Arbeitseinsätze: aktuelles Jahr',
            self::NextEvent => 'Veranstaltung: nächste',
            self::NextWorkAssignment => 'Arbeitseinsatz: nächste',
            self::NextEventOrWorkAssignment => 'Veranstaltung/Arbeitseinsatz: nächste',
            self::Sauna => 'Sauna: Belegungskalender und Anfrage',
            self::PhotoAlbums => 'Fotos: Archiv nach Jahren',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MembershipApplication => 'Online-Beitrittserklärung für Einzelpersonen und Familien.',
            self::EventsCurrentYear, self::EventsFromCurrentYear => 'Liste der veröffentlichten Veranstaltungen.',
            self::WorkAssignmentsCurrentYear => 'Liste der veröffentlichten Arbeitseinsätze.',
            self::NextEvent, self::NextWorkAssignment, self::NextEventOrWorkAssignment => 'Hebt den nächsten anstehenden Termin hervor.',
            self::Sauna => 'Belegungskalender der Sauna mit Anfrageformular.',
            self::PhotoAlbums => 'Fotoarchiv, nach Jahren gruppiert.',
        };
    }

    /**
     * @return list<PageExtensionTextField>
     */
    public function textFields(): array
    {
        return match ($this) {
            self::MembershipApplication => [
                new PageExtensionTextField('eyebrow', 'Kopfzeile über der Überschrift', 'Naturbad Borkheide e.V.'),
                new PageExtensionTextField('title', 'Überschrift', 'Beitrittserklärung'),
                new PageExtensionTextField('intro', 'Einleitung', 'Fülle den Antrag für dich oder deine Familie aus. Weitere Familienmitglieder können direkt ergänzt werden.', multiline: true),
                new PageExtensionTextField('additionalPersonEmailHint', 'Hinweis zur E-Mail-Adresse weiterer Personen', 'Wenn auch diese Person künftig über Neuigkeiten und Informationen rund um den Verein auf dem Laufenden bleiben möchte, trag hier gerne die E-Mail-Adresse ein.', multiline: true),
                new PageExtensionTextField('sepaTitle', 'Überschrift Lastschrift', 'SEPA-Einzugsermächtigung'),
                new PageExtensionTextField('consentsTitle', 'Überschrift Bestätigungen', 'Bestätigungen'),
                new PageExtensionTextField('consentTerms', 'Bestätigung Satzung und Beitragsordnung (Pflicht)', 'Ich erkenne die Vereinssatzung und die Beitragsordnung an.', multiline: true, consent: true),
                new PageExtensionTextField('consentPrivacy', 'Bestätigung Datenschutz (Pflicht)', 'Ich stimme der Verarbeitung meiner Angaben zur Mitgliederverwaltung gemäß Datenschutzerklärung zu.', multiline: true, consent: true),
                new PageExtensionTextField('consentSepa', 'Bestätigung SEPA-Lastschrift (Pflicht)', 'Ich ermächtige den Naturbad Borkheide e.V. widerruflich, die Mitgliedsbeiträge per Lastschrift einzuziehen.', multiline: true, consent: true),
                new PageExtensionTextField('consentEmail', 'Einwilligung E-Mail-Informationen (freiwillig)', 'Ich möchte Informationen des Vereins per E-Mail erhalten.', multiline: true, consent: true),
                new PageExtensionTextField('signerHint', 'Hinweis unter der Unterschrift', 'Mit dem Absenden bestätigst du die Richtigkeit deiner Angaben. Bei Minderjährigen ist der Name der gesetzlichen Vertretung einzutragen.', multiline: true),
                new PageExtensionTextField('submitLabel', 'Beschriftung Absende-Button', 'Mitgliedsantrag verbindlich absenden'),
                new PageExtensionTextField('successTitle', 'Überschrift nach dem Absenden', 'Vielen Dank für deinen Beitrittswunsch'),
                new PageExtensionTextField('successMessage', 'Text nach dem Absenden', 'Vielen Dank. Der Mitgliedsantrag wurde sicher übermittelt.', multiline: true),
            ],
            default => [],
        };
    }

    public function textField(string $key): ?PageExtensionTextField
    {
        foreach ($this->textFields() as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }
}
