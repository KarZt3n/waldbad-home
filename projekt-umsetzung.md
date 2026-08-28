# Projektumsetzung: Website und CMS für das Waldbad Borkheide

## 1. Zweck und Verbindlichkeit

Dieses Dokument beschreibt den fachlichen und technischen Zielumfang für die neue Website des Waldbads Borkheide und das zugehörige Redaktionssystem. Es ist die verbindliche Umsetzungsgrundlage unterhalb von [architektur.md](./architektur.md) und [architektur-patterns.md](./architektur-patterns.md). Bei einem Widerspruch haben die Architekturdokumente Vorrang.

Die Bestandswebsite [waldbad-borkheide.de](https://www.waldbad-borkheide.de/) wurde am 5. August 2026 hinsichtlich Navigation, Inhalten, Formularen, Medien, Downloads und visueller Gestaltung analysiert.

## 2. Produktvision

Es entsteht eine moderne, schnelle, responsive und barrierearme Website für das Waldbad Borkheide. Die gewachsene Identität der bestehenden Website bleibt erkennbar: Natur, Wald, Wasser, das vorhandene Logo sowie eine grüne Grundfarbe mit warmen gelben Akzenten bilden weiterhin die visuelle Basis.

Redaktionelle Inhalte werden nicht fest im Frontend programmiert. Berechtigte Benutzer verwalten sie in einem geschützten CMS-Backend. Veröffentlichte Inhalte stellt das Backend über eine API bereit.

## 3. Verbindliche Systemgrenze

- Das öffentliche Frontend kommuniziert ausschließlich mit API-Controllern.
- Das CMS-Frontend kommuniziert ausschließlich mit API-Controllern.
- API-Controller sind die einzige Schnittstelle zwischen Frontend und Backend.
- Frontends greifen niemals direkt auf Datenbank, Repositories, Entities oder Data-Layer-Komponenten zu.
- API-Controller enthalten keine Geschäftslogik. Sie validieren und transformieren Requests, prüfen den groben Zugriff und delegieren an UseCases oder BusinessQueries der Logic Layer.
- Die API liefert explizite, versionierte JSON-Verträge. Entities werden niemals serialisiert oder nach außen gegeben.
- Öffentliche Lese-Endpunkte und geschützte Redaktions-Endpunkte werden klar getrennt.
- Datei-Uploads erfolgen über geschützte API-Endpunkte. Ausgelieferte Medien verwenden stabile öffentliche URLs oder einen dafür vorgesehenen Media-Endpunkt.

Vorgesehene API-Bereiche:

    /api/public/v1/*   veröffentlichte Website-Inhalte und öffentliche Formulare
    /api/admin/v1/*    CMS, Medien, Moderation, Benutzer und Rechte
    /api/auth/v1/*     Anmeldung, Abmeldung und aktuelle Sitzung

## 4. Analyse der Bestandswebsite

### 4.1 Vorhandene Inhaltsbereiche

Die Bestandswebsite enthält folgende fachliche Bereiche:

- Startseite mit saisonaler Statusmeldung, Veranstaltungsplakat, Einführungstext, Ausstattungsmerkmalen und Bildern
- aktuelle Vereinsmitteilungen, beispielsweise Einladungen zur Mitgliederversammlung
- Werbung für Rettungsschwimmer mit Texten und Plakaten
- Öffnungszeiten, letzter Einlass, Eintrittspreise und Erläuterungen zum Kassensystem
- Jahresveranstaltungsplan mit Datum, Uhrzeit, Titel, Ort und Hinweisen
- Vorstand mit Namen, Funktionen, Zuständigkeiten, E-Mail-Adressen und Porträtbildern
- Satzung, Haus- und Nutzungsordnung sowie Beitrags-/Kassenordnung als PDF-Downloads
- Mitgliedschaft mit Erläuterung und Mitgliedsantrag als PDF
- Foto-, Video- und Ergebnisarchiv, nach Jahren und Veranstaltungen gegliedert, vielfach als externe Links
- Kontakt, Impressum und Datenschutz
- Kontaktformular
- öffentliches Gästebuch mit Eintragsformular, Freigabe vor Veröffentlichung und Seitennavigation
- Unterstützer mit Logo, Beschreibung und externer Verlinkung
- Cookie-Einstellungen für notwendige, analytische und externe Inhalte

### 4.2 Festgestellte redaktionelle Aufgaben

Aus den vorhandenen Inhalten ergeben sich mindestens folgende CMS-Arbeitsabläufe:

- Seiten anlegen, bearbeiten, sortieren, veröffentlichen, zeitgesteuert publizieren und archivieren
- Navigation und sichtbare Seitentitel pflegen
- Status- und Eilmeldungen auf der Startseite aktualisieren
- Texte formatieren und mit Bildern, Downloads, Links, Listen und Hinweisen kombinieren
- Bilder und Dokumente hochladen, ersetzen, beschreiben und wiederverwenden
- Veranstaltungen einzeln pflegen und automatisch chronologisch darstellen
- vergangene Veranstaltungen in ein Archiv überführen
- Öffnungszeiten, Saisonzeiträume und Preispositionen strukturiert bearbeiten
- Vorstandsmitglieder und Zuständigkeiten pflegen und anordnen
- Formulare und Vereinsdokumente aktuell halten
- Medienarchive und externe Foto-, Video- oder Ergebnislinks nach Jahr und Veranstaltung verwalten
- Unterstützer mit Logo, Text, Link und Reihenfolge pflegen
- Gästebucheinträge prüfen, freigeben, ablehnen, ausblenden oder als Spam markieren
- eingehende Kontaktanfragen einsehen und ihren Bearbeitungsstatus pflegen
- Benutzer, Rollen und Berechtigungen administrieren

### 4.3 Gestalterische Ausgangslage

Die Bestandswebsite verwendet ein kräftiges Grün als Flächenfarbe, gelbe Akzente, Natur- und Wasserbilder sowie das Waldbad-Logo. Diese Wiedererkennbarkeit wird erhalten, aber zeitgemäß umgesetzt:

- natürliche Grüntöne als Primärpalette
- warmes Gelb als sparsamer Akzent mit geprüftem Farbkontrast
- helle Inhaltsflächen für bessere Lesbarkeit
- großzügige Abstände, klare Typografie und responsive Karten-/Rasterlayouts
- hochwertige, optimierte Bilddarstellung mit konsistenten Seitenverhältnissen
- gut erkennbare Handlungsaufforderungen und kompakte mobile Navigation
- keine pixelgenaue Kopie des alten Layouts

Konkrete Design-Tokens werden im Design-System festgelegt und müssen die WCAG-Kontrastanforderungen erfüllen.

## 5. Zielgruppen

### 5.1 Öffentliche Besucher

- Badegäste, Familien und Touristen
- Vereinsmitglieder und Interessenten
- Teilnehmer und Besucher von Veranstaltungen
- potenzielle Rettungsschwimmer, Helfer und Ehrenamtliche
- Presse, Partner und Unterstützer

Sie benötigen insbesondere schnell auffindbare Informationen zu aktuellem Status, Öffnungszeiten, Preisen, Ausstattung, Anfahrt, Veranstaltungen und Kontakt.

### 5.2 Interne Benutzer

- Redakteure für allgemeine Inhalte
- Veranstaltungsverantwortliche
- Medienredakteure
- Moderatoren für Gästebuch und Kontaktanfragen
- Veröffentlichende mit Freigaberecht
- Administratoren für Benutzer, Rollen und Systemeinstellungen

## 6. Funktionaler Umfang des öffentlichen Frontends

### 6.1 Globale Funktionen

- responsive Hauptnavigation
- hierarchische Haupt- und Unterseiten mit aufklappbaren Untermenüs
- Startseite mit aktueller Statusinformation
- Brotkrümelnavigation auf tieferen Ebenen, wenn die Seitenstruktur dies erfordert
- Kontakt- und Pflichtlinks im Footer
- konsistente Fehler-, Lade- und Leerzustände
- sprechende, dauerhafte URLs
- Social-Media- und Suchmaschinen-Metadaten pro Seite
- Cookie- und Einwilligungsverwaltung für optionale Dienste
- zugängliche Darstellung auf Smartphone, Tablet und Desktop

### 6.2 Startseite

- Hero-Bereich mit Waldbad-Identität und zentralem Bild
- prominent sichtbarer Betriebsstatus, beispielsweise geöffnet, geschlossen, Saisonpause oder Sonderhinweis
- heutige Öffnungszeit und letzter Einlass
- zentrale Handlungsaufforderungen zu Preisen, Veranstaltungen, Mitgliedschaft und Kontakt
- aktuelle Meldungen
- nächste Veranstaltungen
- Kurzvorstellung und Ausstattung des Waldbads
- ausgewählte Bilder oder Galerien
- optional hervorgehobene Kampagne, beispielsweise Rettungsschwimmer-Suche

### 6.3 Inhaltsseiten und Inhaltsblöcke

Eine Inhaltsseite besteht aus geordneten Inhaltsblöcken. Für das MVP werden benötigt:

- Überschrift
- Rich Text mit sicher begrenzter Formatierung
- Bild
- Bildergalerie
- Text mit Bild
- bereinigtes benutzerdefiniertes HTML für Sonderlayouts
- Einbettung der Inhaltsblöcke einer anderen Seite mit stabiler Seitenreferenz und Schutz vor rekursiven Einbettungen
- Hinweis-/Statusbox
- Link- oder Button-Gruppe
- Download-Liste
- Kontakt-/Personenkarte
- Unterstützerkarte
- Bild-Text-Collection mit gemeinsamer Überschrift, frei wählbaren ein bis vier Spalten und beliebig vielen Karten aus Überschrift, optionalem Rich Text und optionalem Bild
- Veranstaltungsliste
- Veranstaltungsblock mit einfacher Überschrift, Datum, Uhrzeit, optionalen Rich-Text-Zusatzinformationen und optionalem Bild per URL, Medienauswahl oder Upload
- optionale zusätzliche Aktionsbuttons je Veranstaltung mit frei wählbarer Beschriftung und Ziel als URL oder interne CMS-Seite
- Preis-/Öffnungszeiten-Tabelle
- externer Inhalt mit Einwilligungssperre

Benutzerdefiniertes HTML wird ausschließlich nach serverseitiger Bereinigung über eine feste Allowlist veröffentlicht. JavaScript, Event-Handler, Stylesheets, iFrames und unsichere URL-Schemata sind nicht zulässig.

Alle sichtbaren Blocktexte besitzen einen visuellen Editor für Absatz- und Überschriftenformate, Textgröße, Fett, Kursiv, Unterstreichung, Listen, Links und Textfarbe. Das gilt auch für Bild-Text-Blöcke, Hinweise und Handlungsaufrufe. Über einen HTML-Schalter kann derselbe Inhalt alternativ als bereinigter HTML-Quelltext bearbeitet werden. Technische Inhalte wie URLs, Alternativtexte und Linkbeschriftungen bleiben reine Textfelder. Reine Bild-Blöcke besitzen keinen Texteditor.

Beim Einfügen aus der Zwischenablage übernimmt der visuelle Editor ausschließlich reinen Text. Fremde Schriftarten, Größen, Farben, Hintergründe, Links und sonstige HTML-Formatierungen werden nicht übernommen. HTML kann weiterhin bewusst über den HTML-Modus eingegeben werden.

Bild-Text-Blöcke erlauben zusätzlich eine Bildbreite zwischen 20 und 80 Prozent, Bildposition links oder rechts, vertikale Textausrichtung oben/zentriert/unten, horizontale Textausrichtung sowie die Bilddarstellung zugeschnitten oder vollständig. Auf kleinen Viewports werden Bild und Text unabhängig von diesen Einstellungen zugänglich untereinander angeordnet.

Reine Bild-Blöcke erlauben eine Bildbreite zwischen 20 und 100 Prozent sowie eine linksbündige, zentrierte oder rechtsbündige Ausrichtung.

Bild-Text-Collections stellen zusammengehörige Angebote oder Merkmale als responsives Kartenraster dar. Die Redakteurin oder der Redakteur wählt für Desktop ein bis vier Spalten. Jede Karte besitzt eine Überschrift sowie optional Rich Text, Bild, Alternativtext und Bildquelle; Karten können ergänzt, entfernt und umsortiert werden. Auf kleinen Viewports wird das Raster automatisch einspaltig. Der Block „Collection: Bild + Text“ wird ohne Überschrift und Einträge angelegt, damit er für unterschiedliche Inhalte neutral verwendet werden kann.

Für jede veröffentlichte Seite wird ein eigener Veröffentlichungsstand gespeichert. Wird die Seite anschließend bearbeitet und als Entwurf gespeichert, bleiben die zuletzt veröffentlichten Inhalte, der veröffentlichte Slug und die veröffentlichte Navigation im Frontend aktiv. Erst ein erneutes Veröffentlichen ersetzt diesen Stand; „Zurückziehen“ entfernt ihn ausdrücklich aus dem Frontend.

Der Footer enthält eine feste Servicenavigation zu Impressum, Kontakt und Redaktion. Die veröffentlichte CMS-Seite „Impressum“ ist über den Footer erreichbar, wird jedoch nicht zusätzlich in der Hauptnavigation angezeigt.

Die Seite „Unterstützer“ ist Bestandteil der Hauptnavigation. Sie stellt Kooperationspartner in verlinkten Bild-Text-Blöcken mit lokal gespeicherten Logos vor. Bild-Text-Blöcke können dafür optional ein Linkziel am Bild hinterlegen; externe Bildlinks öffnen sicher in einem neuen Tab.

### 6.4 Veranstaltungen

- zukünftige Veranstaltungen chronologisch anzeigen
- Detailseite pro Veranstaltung
- Titel, Kurzbeschreibung und Langtext
- Start- und Endzeit, ganztägige Termine und Zeitzone
- Veranstaltungsort und optionaler externer Kartenlink
- Titelbild oder Plakat und Bildergalerie
- optionale Anmeldung oder externer Anmeldelink
- optionale Ergebnis- und Medienlinks nach der Veranstaltung
- Status: Entwurf, geplant, veröffentlicht, abgesagt, beendet, archiviert
- abgesagte oder geänderte Termine deutlich kennzeichnen

### 6.5 Öffnungszeiten und Preise

- Saisonzeiträume und reguläre Öffnungstage
- abweichende Tage und Sonderöffnungszeiten
- letzter Einlass
- temporäre Schließungen und Hinweise
- strukturierte Preisliste mit Bezeichnung, Preis, Gültigkeit und Erläuterung
- redaktioneller Zusatztext zum Eintritts- und Kassensystem
- keine fest im Frontend eingebauten Preise oder Zeiten

### 6.6 Personen und Verein

- geordnete Darstellung von Vorstandsmitgliedern
- Name, Rolle, Aufgabenbereich, Kontaktmöglichkeit und optionales Porträt
- konfigurierbare Sichtbarkeit einzelner Kontaktdaten
- Satzung, Ordnungen und weitere Vereinsdokumente als versionierte Downloads
- Mitgliedschaftsseite mit jeweils aktuellem Antrag

### 6.7 Medienarchiv

- Galerien nach Jahr und Veranstaltung filtern
- eigene Bilder und externe Galerien unterstützen
- Video- und Ergebnislinks verwalten
- Vorschaubild, Titel, Datum, Beschreibung und Quelle darstellen
- externe Inhalte erst nach erforderlicher Einwilligung laden

### 6.8 Unterstützer

- Logo, Name, Kurzbeschreibung und Ziel-URL
- manuell steuerbare Reihenfolge
- optionale Zeitsteuerung und Sichtbarkeit
- externe Links sicher und klar gekennzeichnet öffnen

### 6.9 Kontaktformular

- Name
- E-Mail-Adresse
- optionaler Betreff oder Themenbereich
- Nachricht
- Datenschutzbestätigung
- Spam-Schutz und serverseitiges Rate-Limiting
- neutrale Erfolgsantwort ohne interne Details
- Speicherung oder Weiterleitung nach konfigurierbarem fachlichem Prozess
- keine vertraulichen Inhalte in Anwendungslogs

### 6.10 Gästebuch

- öffentliche, paginierte Liste freigegebener Einträge
- Eintragsformular mit Anzeigename, optionaler E-Mail-Adresse und Nachricht
- E-Mail-Adressen werden niemals öffentlich angezeigt
- jeder neue Eintrag erhält zunächst den Status pending
- Veröffentlichung erst nach manueller Freigabe
- Spam-Schutz, Rate-Limiting und sichere Inhaltsbereinigung
- nachvollziehbare Moderationsentscheidungen

## 7. CMS-Backend

### 7.1 Dashboard

- Schnellzugriff auf häufige Redaktionsaufgaben
- Entwürfe und geplante Veröffentlichungen
- ausstehende Gästebuchmoderation
- neue oder offene Kontaktanfragen
- kommende Veranstaltungen
- Hinweise auf fehlende Pflichtangaben, beispielsweise Alt-Texte

### 7.2 Seitenverwaltung

- Seiten erstellen, als ausgeblendeten Entwurf duplizieren, bearbeiten, archivieren und nach Bestätigung dauerhaft löschen
- Haupt- und Unterseiten in einem aufklappbaren Strukturbaum anzeigen
- Hauptseiten sowie Unterseiten direkt über Plus-Symbole am gewünschten Knoten anlegen und über Bearbeiten-Symbole öffnen
- Hauptseiten und Unterseiten innerhalb ihrer jeweiligen Navigationsebene nach oben und unten verschieben, ohne den Veröffentlichungsstatus zu verändern
- Startseite sowie Seiten mit Unterseiten oder bestehenden Einbettungsreferenzen gegen Löschen schützen
- Elternseite einer Seite ändern; Selbstbezüge und zyklische Seitenstrukturen serverseitig verhindern
- Seiten unabhängig vom Veröffentlichungsstatus für das gesamte Frontend sichtbar oder unsichtbar schalten; unsichtbare Seiten bleiben im Backend erhalten
- Titel, Navigationsbezeichnung, Slug und Seitenstatus pflegen
- Slug, Navigationsbezeichnung und SEO-Titel beim Setzen des Seitentitels automatisch vorbelegen, ohne spätere manuelle Anpassungen zu überschreiben
- Inhaltsblöcke hinzufügen, bearbeiten, verschieben und entfernen
- Einfügepunkte zwischen allen Inhaltsblöcken sowie Sortierung per Drag-and-drop und Hoch-/Runter-Schaltflächen
- Entwurfsvorschau ohne öffentliche Veröffentlichung
- sofortige oder geplante Veröffentlichung
- Veröffentlichung zurückziehen
- Änderungshistorie und Wiederherstellung einer vorherigen Version
- SEO-Titel, Beschreibung, Social-Media-Bild und Indexierungsstatus
- Konflikterkennung bei paralleler Bearbeitung über Optimistic Locking

### 7.3 Navigationsverwaltung

- Menüeinträge anlegen, benennen, sortieren und verschachteln
- interne Seiten, Downloads und externe URLs verlinken
- Sichtbarkeit und Veröffentlichungszeitraum steuern
- ungültige oder unveröffentlichte Ziele erkennen

### 7.4 Medienbibliothek

- Bilder und Dokumente hochladen
- Dateityp, Größe und Inhalt serverseitig validieren
- Titel, Alt-Text, Beschreibung, Copyright/Urheber, Quelle und Fokuspunkt pflegen
- automatische Bildvarianten und moderne Ausgabeformate erzeugen
- Medien suchen, filtern und wiederverwenden
- Verwendungsnachweis vor dem Löschen anzeigen
- nicht mehr verwendete Medien sicher archivieren
- PDF-Dokumente versionieren und mit einem sichtbaren Stand versehen

### 7.5 Strukturierte Fachmodule

Das CMS stellt eigene Verwaltungsbereiche bereit für:

- Meldungen und Betriebsstatus
- Veranstaltungen
- Öffnungszeiten, Ausnahmen und Preise
- Personen und Vorstand
- Dokumente und Downloads
- Galerien und externe Medienlinks
- Unterstützer
- Gästebucheinträge
- Kontaktanfragen
- globale Website-Einstellungen

Fachlich strukturierte Daten werden nicht als unstrukturierter Rich Text gespeichert.

In der Gästebuchverwaltung werden neue Einträge mit dem Status `pending` als primäre Arbeitsliste dargestellt. Bereits veröffentlichte Einträge erscheinen gesammelt in einem standardmäßig geschlossenen Archivbereich und können dort nachträglich abgelehnt oder als Spam markiert werden. Abgelehnte Einträge und Spam bleiben in einem separaten aufklappbaren Bereich erreichbar und können erneut veröffentlicht werden.

### 7.6 Vorschau und Veröffentlichungsworkflow

Inhalte verwenden mindestens folgende Zustände:

    draft -> in_review -> published -> archived

Zusätzlich sind zeitgesteuerte Veröffentlichungen und das Zurückziehen veröffentlichter Inhalte möglich. Kleine Teams dürfen den Review-Schritt per Berechtigung überspringen; die Statusänderung bleibt dennoch im Audit-Log nachvollziehbar.

## 8. Benutzer-, Rollen- und Rechtemanagement

### 8.1 Grundprinzipien

- Zugriff wird serverseitig für jeden geschützten API-Aufruf geprüft.
- Das Ausblenden einer Funktion im CMS-Frontend ist keine Autorisierungsprüfung.
- Berechtigungen werden als klar benannte Fähigkeiten modelliert, nicht als im Code verteilte Rollenabfragen.
- Rollen bündeln Berechtigungen. Sonderrechte pro Benutzer sind nur bei tatsächlichem Bedarf vorzusehen.
- Das Prinzip der geringsten Berechtigung gilt standardmäßig.
- Der letzte aktive Super-Administrator darf nicht gelöscht oder gesperrt werden.

### 8.2 Globale und modulbezogene Rollen

Ein Benutzer besitzt optional genau eine globale Rolle:

| Rolle | Zweck |
|---|---|
| SuperAdmin | Vollzugriff innerhalb der explizit freigeschalteten Module sowie Verwaltung von SuperAdmins und geschützten Systemeinstellungen |
| Admin | Vollzugriff innerhalb der explizit freigeschalteten Module; keine Bearbeitung oder Sperrung von SuperAdmins |

Die fachlichen Rechte werden pro Modul zugewiesen. Das Modul `pages` kennt `Viewer`, `Editor`, `Publisher` und `Moderator`. Alle anderen Module kennen `Viewer` und `Editor`. Admin und SuperAdmin heben eine zugewiesene Modulrolle innerhalb des Moduls auf Vollzugriff an, schalten aber keine weiteren Module frei.

### 8.3 Berechtigungsmatrix

| Modul | Rollen |
|---|---|
| Seiten | Viewer: lesen; Editor: bearbeiten; Publisher: bearbeiten und veröffentlichen; Moderator: bearbeiten und redaktionell prüfen |
| Aktivitäten | Viewer: lesen; Editor: bearbeiten |
| Gästebuch | Viewer: lesen; Editor: moderieren |
| Kontaktanfragen | Viewer: lesen; Editor: Status bearbeiten |
| Veranstaltungshelfer | Viewer: lesen; Editor: Teilnahme und Zeiten bearbeiten |
| Mitgliedsanträge | Viewer: lesen; Editor: fachliche Aktionen ausführen |
| Benutzerverwaltung | Viewer: lesen; Editor: Benutzer und Zugriffe gemäß globaler Schutzstufe verwalten |

### 8.4 Benutzerverwaltung

- Benutzer anlegen und einladen
- Namen und E-Mail-Adresse verwalten
- Rollen zuweisen und entziehen
- Benutzer aktivieren und sperren
- Passwort-zurücksetzen-Prozess auslösen
- aktive Sitzungen widerrufen
- letzten erfolgreichen Login anzeigen
- sicherheitsrelevante Aktionen auditieren
- Benutzer nicht hart löschen, wenn ihnen Audit-Einträge zugeordnet sind

## 9. Authentifizierung und Sicherheit

- sichere, serverseitig verwaltete Anmeldung für das CMS
- sichere Passwort-Hashes nach aktuellem Symfony-Standard
- Login-Rate-Limiting und Schutz gegen Credential Stuffing
- CSRF-Schutz bei cookie-basierter Authentifizierung
- sichere Cookie-Attribute: HttpOnly, Secure und eine geeignete SameSite-Einstellung
- Session-Rotation nach erfolgreichem Login und Rechteänderungen
- keine Benutzerexistenz durch unterschiedliche Login- oder Reset-Antworten offenlegen
- optional vorbereitete Mehrfaktor-Authentifizierung; für privilegierte Rollen empfohlen
- serverseitige Autorisierung auf UseCase-Ebene für fachliche Rechte
- Upload-Validierung, zufällige interne Dateinamen und keine Ausführung hochgeladener Dateien
- HTML-Bereinigung für Rich Text und öffentliche Eingaben
- Rate-Limiting für Kontakt- und Gästebuch-Endpunkte
- Sicherheitsereignisse protokollieren, ohne Geheimnisse oder private Nachrichten zu loggen

## 10. Fachliche Module und Layer-Zuordnung

Die Umsetzung folgt der vorgegebenen UI-/Logic-/Data-Architektur:

| Modul | Verantwortung |
|---|---|
| Content | Seiten, Blöcke, Versionen, Veröffentlichungsworkflow |
| Navigation | Menüs, Reihenfolge und Linkziele |
| Media | Bilder, Dokumente, Metadaten und Varianten |
| OpeningHours | Saisonzeiten, Ausnahmen, Betriebsstatus und letzter Einlass |
| Pricing | Preisgruppen, Gültigkeit und Erläuterungen |
| Event | Veranstaltungen, Status, Archiv und zugehörige Medien |
| Organisation | Vorstand, Funktionen und Kontaktdaten |
| Partner | Unterstützer, Logos und Links |
| Guestbook | Einreichung, Moderation und Veröffentlichung |
| Contact | Kontaktanfragen und Bearbeitungsstatus |
| IdentityAccess | Benutzer, Rollen, Berechtigungen und Sitzungen |
| SiteSettings | globale Einstellungen und Social-/SEO-Standardwerte |
| Audit | unveränderbare Nachvollziehbarkeit administrativer Aktionen |

Jedes Modul wird innerhalb von src/UI, src/Logic und src/Data symmetrisch abgebildet. Modulübergreifende Kommunikation folgt dem Adapter-Prinzip aus den Architekturdokumenten.

## 11. API-Anforderungen

### 11.1 Öffentliche Lese- und Formular-API

Mindestens erforderlich:

    GET  /api/public/v1/site
    GET  /api/public/v1/navigation
    GET  /api/public/v1/pages/{slug}
    GET  /api/public/v1/status
    GET  /api/public/v1/opening-hours
    GET  /api/public/v1/prices
    GET  /api/public/v1/events
    GET  /api/public/v1/events/{slug}
    GET  /api/public/v1/board-members
    GET  /api/public/v1/documents
    GET  /api/public/v1/galleries
    GET  /api/public/v1/partners
    GET  /api/public/v1/guestbook-entries
    POST /api/public/v1/guestbook-entries
    POST /api/public/v1/contact-requests

### 11.2 Authentifizierungs-API

Mindestens erforderlich:

    POST /api/auth/v1/login
    POST /api/auth/v1/logout
    GET  /api/auth/v1/me
    POST /api/auth/v1/password-reset-requests
    POST /api/auth/v1/password-resets

### 11.3 Redaktions-API

Für jedes verwaltete Modul werden nur die fachlich benötigten Lese- und Schreiboperationen angeboten. Generische, unbeschränkte CRUD-Endpunkte sind zu vermeiden. Zusätzlich werden explizite Aktionen benötigt:

    POST /api/admin/v1/pages/{id}/request-review
    POST /api/admin/v1/pages/{id}/publish
    POST /api/admin/v1/pages/{id}/unpublish
    POST /api/admin/v1/pages/{id}/restore
    POST /api/admin/v1/guestbook-entries/{id}/approve
    POST /api/admin/v1/guestbook-entries/{id}/reject
    POST /api/admin/v1/guestbook-entries/{id}/mark-spam
    POST /api/admin/v1/users/{id}/suspend
    POST /api/admin/v1/users/{id}/revoke-sessions

### 11.4 API-Konventionen

- JSON als Standardformat
- ISO-8601 für Datum und Zeit; fachliche Zeitzone Europe/Berlin
- stabile technische IDs und separate sprechende Slugs
- einheitliches Fehlerformat mit Fehlercode, verständlicher Meldung und optionalen Feldfehlern
- Pagination, Filter und Sortierung für Listen
- ETag oder Versionsfeld für konfliktbehaftete Bearbeitungen
- keine internen Exception-Texte, Stacktraces oder Datenbankdetails in Responses
- OpenAPI-Beschreibung als ausführbarer API-Vertrag

## 12. Zentrale Business-Modelle

Voraussichtlich werden mindestens benötigt:

- Page, PageVersion, ContentBlock und Publication
- Navigation und NavigationItem
- MediaAsset, Document, Gallery und GalleryItem
- OpeningSchedule, OpeningException und OperationalStatus
- PriceItem
- Event
- BoardMember
- Partner
- GuestbookEntry und GuestbookModeration
- ContactRequest
- User, Role und Permission
- AuditEntry

Persistenz-Entities verbleiben gemäß Architektur ausschließlich in der Data Layer und werden dort auf diese Models abgebildet.

## 13. Nichtfunktionale Anforderungen

### 13.1 Barrierefreiheit

- Zielniveau WCAG 2.2 AA
- vollständige Tastaturbedienbarkeit
- sichtbare Fokuszustände
- semantische Überschriften und Landmarken
- aussagekräftige Alternativtexte oder bewusst leere Alt-Texte für dekorative Bilder
- Formularfehler programmatisch zuordenbar und verständlich formuliert
- ausreichende Farbkontraste trotz grün-gelber Markenwelt
- keine Information ausschließlich über Farbe vermitteln

### 13.2 Performance

- responsive Bildvarianten und moderne Bildformate
- Lazy Loading für nicht sofort sichtbare Medien
- kleine initiale Frontend-Bundles
- cachebare öffentliche API-Antworten
- keine blockierenden Drittanbieter-Inhalte ohne Einwilligung
- Core Web Vitals als messbare Qualitätsziele

### 13.3 SEO und Auffindbarkeit

- serverseitig auslieferbare oder vorgerenderte öffentliche Seiten, sofern das Frontend dies benötigt
- eindeutige Seitentitel und Meta-Beschreibungen
- kanonische URLs
- Open-Graph-Metadaten
- XML-Sitemap und robots.txt
- strukturierte Daten für Organisation und Veranstaltungen, soweit inhaltlich korrekt
- Weiterleitungen für relevante URLs der Bestandswebsite

### 13.4 Datenschutz

- Datensparsamkeit bei Gästebuch, Kontakt und Benutzerkonten
- definierte Aufbewahrungs- und Löschfristen für Kontaktanfragen, abgelehnte Gästebucheinträge und Audit-Daten
- externe Medien und Analytik nur nach erforderlicher Einwilligung
- rechtliche Texte vor Produktivgang fachlich/rechtlich prüfen; dieses Dokument ersetzt keine Rechtsberatung
- nachvollziehbare Datenflüsse und Auftragsverarbeiter

### 13.5 Betrieb und Beobachtbarkeit

- strukturierte Logs mit Korrelations-ID
- Health-Checks für Anwendung, Datenbank und notwendige Infrastruktur
- Fehlerüberwachung ohne Speicherung sensibler Formularinhalte
- automatisierte, regelmäßig getestete Backups
- Audit-Log für Login, Rechteänderungen, Publikation, Moderation und Lösch-/Archivierungsaktionen

## 14. Migration der Bestandsinhalte

Vor dem Produktivgang werden die relevanten Inhalte der Bestandswebsite inventarisiert und fachlich bereinigt. Die Migration umfasst:

- Seiten und Navigation
- aktuelle und zukünftige Veranstaltungen
- Öffnungszeiten und Preise
- Vorstandsmitglieder und Zuständigkeiten
- aktuelle PDF-Dokumente
- Bilder mit geklärten Nutzungsrechten, Alt-Texten und Urheberangaben
- historische Medienlinks und Ergebnislisten
- Unterstützer
- ausgewählte Gästebucheinträge nur nach geklärter datenschutzrechtlicher Grundlage

Veraltete, doppelte oder rechtlich ungeklärte Inhalte werden nicht automatisch übernommen. Für bisherige öffentliche URLs wird eine Redirect-Liste erstellt.

## 15. MVP-Abgrenzung

### 15.1 Bestandteil des MVP

- öffentliches responsives Frontend
- API-only-Kommunikation
- CMS-Login
- Benutzer, Rollen und Berechtigungen
- Seiten und blockbasierte Inhalte
- Medienbibliothek und PDF-Downloads
- Navigation
- Betriebsstatus, Öffnungszeiten und Preise
- Veranstaltungen
- Vorstand
- Mitgliedschaft und Vereinsdokumente
- Galerien und externe Medienlinks
- Unterstützer
- Kontaktformular
- moderiertes Gästebuch
- Entwurf, Review, Publikation, Archiv und Audit-Log
- Cookie-/Consent-Verwaltung
- Migration der aktuellen Kerninhalte

### 15.2 Zunächst nicht Bestandteil

- Online-Ticketverkauf oder Zahlungsabwicklung
- qualifizierte elektronische Signatur für die digitale Vereinsaufnahme
- vollständige Mitgliederverwaltung
- Dienstplanung für Rettungsschwimmer
- Newsletter-Marketing-Automation
- frei installierbare CMS-Plugins
- ungeprüftes HTML, JavaScript oder frei ausführbarer Code durch Redakteure

Diese Funktionen dürfen später als eigene fachliche Erweiterungen geplant werden.

## 16. Empfohlene Umsetzungsreihenfolge

### Phase 1: Fundament

- API-Konventionen und OpenAPI-Grundvertrag
- Authentifizierung, Benutzer, Rollen und Berechtigungen
- Audit-Log
- Content-, Navigation- und Media-Grundmodelle
- Design-System und Frontend-Grundlayout

### Phase 2: Kernwebsite

- Seiten und Inhaltsblöcke
- Vorschau und Veröffentlichungsworkflow
- Startseite
- Öffnungszeiten, Preise und Betriebsstatus
- Veranstaltungen
- SEO, Barrierefreiheit und Redirect-Grundlage

### Phase 3: Vereinsinhalte

- Vorstand
- Dokumente und Mitgliedschaft
- Galerien und externe Medien
- Unterstützer

### Phase 4: Interaktion und Moderation

- Kontaktformular
- Gästebuch
- Moderationsoberfläche
- Benachrichtigungen und Rate-Limiting

### Phase 5: Migration und Produktivsetzung

- Inhaltsmigration und redaktionelle Qualitätsprüfung
- Sicherheits-, Performance- und Barrierefreiheitstests
- Schulung der Redakteure
- Backup-/Restore-Test
- kontrollierter Go-live mit Redirects und Monitoring

## 17. Abnahmekriterien

- Alle öffentlichen Inhalte stammen aus der API; im Frontend existieren keine redaktionellen Schattenkopien.
- Kein Frontend greift direkt auf Backend-Interna oder die Datenbank zu.
- Ein Editor kann ohne Codeänderung Texte, Bilder, Downloads und strukturierte Fachinhalte pflegen.
- Ein Editor kann nicht veröffentlichen, solange ihm das entsprechende Recht fehlt.
- Ein Publisher kann Änderungen prüfen, veröffentlichen, zurückziehen und eine frühere Version wiederherstellen.
- Ein Moderator kann Gästebucheinträge freigeben oder ablehnen, ohne sonstige Inhalte bearbeiten zu dürfen.
- Ein Administrator kann Benutzer sperren und Rollen zuweisen; nur ein SuperAdmin kann das Berechtigungsmodell verändern.
- Unveröffentlichte Inhalte sind über die Public API nicht abrufbar.
- Öffnungszeiten, Preise und Veranstaltungstermine sind strukturiert und werden nicht als fest formatierter Seitentext gepflegt.
- Bilder können einen Alternativtext erhalten; ein leeres Feld kennzeichnet das Bild bewusst als dekorativ.
- Konflikte bei paralleler Bearbeitung überschreiben keine fremden Änderungen unbemerkt.
- Gästebucheinträge erscheinen erst nach Freigabe; private E-Mail-Adressen werden nie veröffentlicht.
- Kritische administrative Aktionen sind im Audit-Log nachvollziehbar.
- Die wesentlichen Besucheraufgaben sind mobil, ohne horizontales Scrollen und per Tastatur nutzbar.
- Relevante Bestands-URLs werden auf die neuen Ziele weitergeleitet.

## 18. Vor Implementierungsbeginn zu konkretisieren

Folgende Entscheidungen werden in kurzen Architecture Decision Records festgehalten, bevor die jeweils betroffene Umsetzung beginnt:

- konkrete Frontend-Technologie und Rendering-Strategie
- Authentifizierungsverfahren und optionale Mehrfaktor-Authentifizierung
- Medien-Speicher und Bildverarbeitung
- E-Mail-Versand und Zustellung von Kontaktbenachrichtigungen
- Umfang und technische Umsetzung der Inhaltsvorschau
- zulässige Analyse-/Tracking-Dienste
- Aufbewahrungsfristen und Datenschutzprozess
- Hosting-, Deployment- und Backup-Strategie

Diese offenen technischen Entscheidungen ändern nicht die verbindliche Vorgabe, dass beide Frontends ausschließlich über API-Controller mit dem Backend kommunizieren.

## 19. Fachliche Erweiterung: digitaler Mitgliedsantrag

Der digitale Mitgliedsantrag ist als erste gekapselte CMS-Erweiterung umgesetzt. Eine Seite bindet ihn über den Blocktyp `extension` mit dem Schlüssel `membership_application` ein. Das öffentliche Frontend rendert daraus ein Formular für Einzelpersonen oder Familien mit bis zu acht Personen, Kontaktdaten, SEPA-Daten und den erforderlichen Bestätigungen.

Die API speichert Anträge normalisiert mit dem Status `pending`. IBAN-Daten werden auf ausdrücklichen fachlichen Wunsch im Klartext gespeichert und in der zugriffsgeschützten Redaktionsoberfläche vollständig ausgegeben. Der Zugriff bleibt auf Benutzer mit dem freigeschalteten Modul `membership_applications` beschränkt. Administratoren können den Bearbeitungsstand ansehen und fehlgeschlagene Übertragungen erneut bereitstellen.

Für die Übergabe an ein Fremdsystem steht eine maschinenlesbare Pull-Schnittstelle bereit:

- `POST /api/integration/v1/membership-applications/claim` reserviert offene Anträge und setzt sie auf `processing`.
- `POST /api/integration/v1/membership-applications/{id}/complete` bestätigt die Übernahme mit einer Fremdsystem-Referenz und setzt den Status auf `done`.
- `POST /api/integration/v1/membership-applications/{id}/fail` dokumentiert einen Übertragungsfehler und setzt den Status auf `failed`.

Die Integrations-API ist standardmäßig deaktiviert. Für den Betrieb muss `MEMBERSHIP_INTEGRATION_TOKEN` als geheimes Deployment- beziehungsweise Laufzeit-Secret gesetzt werden. Der Client sendet es ausschließlich als `Authorization: Bearer <Token>`. Das Token gehört weder in das Repository noch in CMS-Inhalte.

Die digitale Bestätigung besteht aus dem ausgeschriebenen Namen der unterzeichnenden Person und verpflichtenden Zustimmungen zu Satzung/Beitragsordnung, Datenschutz und SEPA-Ermächtigung. Eine qualifizierte elektronische Signatur ist nicht Bestandteil dieser Ausbaustufe.

## 20. Fachliche Erweiterung: Veranstaltungshelfer

Ein Veranstaltungsblock kann optional die Helferanmeldung aktivieren. Die zugehörige Button-Beschriftung und Aktivitätszuordnung werden im Editor nur bei aktivierter Helferanmeldung angezeigt. Aktivierte Veranstaltungen erhalten eine stabile Kennung und zeigen im öffentlichen Frontend den konfigurierbaren Button „Ich möchte helfen!“. Dieser öffnet ein Dialogformular mit Vorname, Nachname, optionaler Freitextnachricht und Datenschutzbestätigung. Unabhängig davon kann eine Veranstaltung mehrere weitere Aktionsbuttons erhalten, die wahlweise auf eine sichere URL oder eine interne CMS-Seite verweisen.

Die öffentliche API akzeptiert ausschließlich reinen Text. Jede Anmeldung wird mit der stabilen Veranstaltungskennung sowie einer Momentaufnahme aus Titel, Datum und Uhrzeit gespeichert. Dadurch bleibt die fachliche Zuordnung erhalten, wenn eine Veranstaltung später umbenannt, verschoben oder archiviert wird. In der Verwaltungsansicht werden Titel, Datum und Uhrzeit anhand der stabilen Kennung aus dem aktuellen CMS-Stand ergänzt, damit Terminänderungen unmittelbar sichtbar sind. Ist die Veranstaltung nicht mehr vorhanden, dient die gespeicherte Momentaufnahme als Rückfallanzeige.

Moderatoren und Administratoren sehen die Anmeldungen im Redaktionsbereich „Veranstaltungshelfer“, gruppiert nach Veranstaltung. Nach der Veranstaltung wird je Anmeldung „Hat teilgenommen“ mit einem oder mehreren Von-bis-Zeiträumen oder „Nicht teilgenommen“ ohne Teilnahmezeit erfasst. Zeiträume können nachträglich ergänzt, bearbeitet oder entfernt werden und dürfen sich nicht überschneiden. Aus allen Zeiträumen wird die Gesamtzeit minutengenau berechnet. Für die öffentliche Übermittlung gilt im Produktivbetrieb ein Rate-Limit; in der DDEV- und Testumgebung ist es deaktiviert.

Die Arbeitsliste priorisiert Veranstaltungen nach ihrem Datum: heutige Termine stehen an erster Stelle, anschließend folgen zukünftige Termine chronologisch. Bereits vergangene Veranstaltungen des laufenden Jahres befinden sich in einem geschlossenen Bereich „Abgeschlossene Veranstaltungen“. Ältere Veranstaltungen bleiben nach Jahren absteigend gruppiert in separaten Archiv-Accordions erreichbar.

### Wiederverwendbare Aktivitäten

Im Redaktionsbereich „Aktivitäten“ werden Tätigkeiten wie „Aufbau“, „Abbau“, „Bierwagen“ oder „Wasserbecken entmoosen“ zentral angelegt, beschrieben und bei Bedarf deaktiviert. Dieselbe Aktivität kann mehreren Veranstaltungen zugeordnet werden. Die benötigte Helferzahl gehört zur jeweiligen Zuordnung und kann deshalb je Veranstaltung unterschiedlich sein.

Das gilt sowohl für reguläre Veranstaltungen als auch für Arbeitseinsätze auf „Gemeinsam anpacken“: Beide werden als Veranstaltungsblock gepflegt und erhalten die passenden Aktivitäten. Im öffentlichen Helferformular werden die Tätigkeiten als Checkboxen mit Soll- und Anmeldezahl angezeigt. Bei konfigurierten Aktivitäten muss mindestens eine ausgewählt werden. Die Auswahl wird an der Helferanmeldung als historischer Snapshot gespeichert und in „Veranstaltungshelfer“ bei der jeweiligen Person angezeigt.

## 21. Modulbezogenes Rechtemanagement

Jeder CMS-Benutzer erhält mindestens ein fachliches Modul mit einer darin geltenden Rolle. Dadurch lassen sich beispielsweise `pages: editor` und `activities: viewer` unabhängig kombinieren.

Verfügbare Module sind Seiten, Aktivitäten, Gästebuch, Kontaktanfragen, Veranstaltungshelfer, Mitgliedsanträge und Benutzerverwaltung. Nicht freigeschaltete Module werden in der Redaktionsnavigation nicht angezeigt und ihre Admin-API-Endpunkte antworten mit HTTP 403. Das Seitenmodul darf Aktivitäten lesend laden, damit bestehende Aktivitätszuordnungen in Veranstaltungsblöcken dargestellt werden können; das Anlegen und Bearbeiten von Aktivitäten benötigt weiterhin das Modul Aktivitäten.

Admin und SuperAdmin erhalten innerhalb ihrer freigeschalteten Module alle fachlichen Rechte. Auch diese globalen Rollen erhalten keinen Zugriff auf nicht zugewiesene Module. Admins dürfen keine SuperAdmins bearbeiten oder sperren; nur SuperAdmins dürfen die globale Rolle SuperAdmin vergeben. Die vollständige Matrix und die serverseitigen Schutzregeln stehen in [benutzer-konzept.md](./benutzer-konzept.md).

Benutzer ohne globale Administratorrolle können im Modul Seiten zusätzlich auf ausdrücklich ausgewählte Seiten eingeschränkt werden. Pro Seite wird ein Bearbeitungs- oder Veröffentlichungsrecht vergeben. Eingeschränkte Benutzer sehen in der Seitenverwaltung ausschließlich diese Seiten; direkte API-Aufrufe für andere Seiten werden ebenfalls abgewiesen. Admin und SuperAdmin sehen innerhalb ihres freigeschalteten Seitenmoduls unabhängig von solchen Einträgen immer alle Seiten.
