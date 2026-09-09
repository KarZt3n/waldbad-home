<?php

namespace App\Logic\Settings\Email\Model;

/**
 * Rein für die Oberfläche: liefert sinnvolle SMTP-Vorbelegungen für die üblichen Anbieter dieses
 * Vereins, damit Host/Port nicht jedes Mal nachgeschlagen werden müssen. Hat keinen Einfluss auf
 * den tatsächlichen Versand — maßgeblich sind ausschließlich die auf `EmailSettings` gespeicherten
 * Host-/Port-/Zugangsdaten, unabhängig davon, welcher Preset zuletzt gewählt wurde.
 *
 * Google: Persönliches/kostenloses Gmail-Konto akzeptiert weiterhin SMTP mit einem App-Passwort
 * (erfordert 2-Faktor-Authentifizierung; das normale Kontopasswort funktioniert seit der
 * Abschaltung des einfachen Logins nicht mehr). Für ein Google-Workspace-Konto der Domain selbst
 * ist seit Frühjahr 2025 für den direkten smtp.gmail.com-Login OAuth2 vorgeschrieben — praktikabel
 * ohne OAuth-Anmeldefluss ist stattdessen der Google-Workspace-„SMTP-Relay-Dienst“
 * (smtp-relay.gmail.com), der in der Google-Admin-Konsole freigeschaltet wird (Absender-IP-Freigabe
 * oder weiterhin Benutzername/Passwort, je nach dortiger Konfiguration).
 *
 * Telekom/Domain-Hosting: Die MX-Einträge von waldbad-borkheide.de zeigen auf T-Online
 * (smtp-*.tld.t-online.de, SPF via hier-im-netz.de) — das Postfach der Domain liegt also beim
 * Telekom-„Homepage Center“ (ehem. „hier-im-netz.de“/Homepage-Baukasten). Aktueller SMTP-Server
 * dafür ist securesmtp.t-online.de, Login mit voller E-Mail-Adresse und Postfach-Passwort.
 */
enum EmailProviderPreset: string
{
    case Google = 'google';
    case Telekom = 'telekom';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google (Gmail / Google Workspace)',
            self::Telekom => 'Telekom / Homepage Center (waldbad-borkheide.de)',
            self::Custom => 'Benutzerdefiniert',
        };
    }

    public function defaultHost(): ?string
    {
        return match ($this) {
            self::Google => 'smtp.gmail.com',
            self::Telekom => 'securesmtp.t-online.de',
            self::Custom => null,
        };
    }

    public function defaultPort(): ?int
    {
        return match ($this) {
            self::Google, self::Telekom => 587,
            self::Custom => null,
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Google => 'Persönliches Gmail-Konto: Benutzername = volle Gmail-Adresse, Passwort = ein App-Passwort '
                .'(erfordert 2-Faktor-Authentifizierung in den Google-Kontoeinstellungen, das normale Passwort wird nicht '
                .'mehr akzeptiert). Für ein Google-Workspace-Konto stattdessen den „SMTP-Relay-Dienst“ in der Google-Admin-'
                .'Konsole freischalten und dessen Zugangsdaten bzw. Host hier eintragen (smtp-relay.gmail.com) — der direkte '
                .'Login über smtp.gmail.com verlangt bei Workspace inzwischen OAuth2, was diese einfache PIN-Verwaltung nicht abbildet.',
            self::Telekom => 'Ermittelt aus den MX-Einträgen von waldbad-borkheide.de (Telekom „Homepage Center“, vormals '
                .'hier-im-netz.de). Benutzername = volle E-Mail-Adresse des Postfachs, Passwort = das dazugehörige Postfach-Passwort.',
            self::Custom => 'Host, Port und Zugangsdaten eines beliebigen anderen Anbieters eintragen.',
        };
    }
}
