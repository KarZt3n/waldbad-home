# Benutzer-, Rollen- und Modulkonzept

## 1. Grundprinzip

Das Waldbad-CMS vergibt Berechtigungen **pro Modul**. Eine Modulzuordnung besteht immer aus dem Modul und genau einer darin geltenden Rolle. Nicht zugewiesene Module sind weder im CMS-Menü sichtbar noch über die Admin-API erreichbar.

Zusätzlich kann ein Benutzer optional genau eine globale Administratorrolle besitzen:

- `Admin`
- `SuperAdmin`

Admin und SuperAdmin erhalten in jedem ihnen zugewiesenen Modul die höchste fachliche Berechtigung. Sie erhalten dadurch aber **keinen Zugriff auf nicht freigeschaltete Module**.

Die sichtbare Navigation ist nur eine Bedienhilfe. Jeder geschützte API-Endpunkt prüft die Berechtigung zusätzlich serverseitig.

## 2. Module

| Modul | Technischer Schlüssel | Inhalt |
|---|---|---|
| Seiten | `pages` | Seiten, Navigation, Inhaltsblöcke und Medienbilder |
| Aktivitäten | `activities` | Wiederverwendbare Helferaktivitäten |
| Gästebuch | `guestbook` | Prüfung und Moderation von Gästebucheinträgen |
| Kontaktanfragen | `contact_requests` | Einsicht und Bearbeitung öffentlicher Kontaktanfragen |
| Veranstaltungshelfer | `event_helpers` | Helferanmeldungen, Teilnahme und Einsatzzeiten |
| Mitgliedsanträge | `membership_applications` | Einsicht und Bearbeitungsstatus digitaler Mitgliedsanträge |
| Benutzerverwaltung | `user_management` | Benutzer anlegen, anzeigen, sperren und Berechtigungen verwalten |

Jeder Benutzer benötigt mindestens eine Modulzuordnung.

## 3. Modulrollen

### 3.1 Seiten

| Rolle | Rechte |
|---|---|
| `Viewer` | Seiten und Medien lesen |
| `Editor` | lesen, Seiten und Medien anlegen und bearbeiten, Vorschau verwenden und zur Prüfung einreichen |
| `Publisher` | alle Editor-Rechte sowie veröffentlichen und zurückziehen |
| `Moderator` | alle Editor-Rechte sowie redaktionelle Prüfung; keine Veröffentlichung |

Für Benutzer ohne globale Administratorrolle kann der Zugriff optional auf einzelne Seiten eingeschränkt werden. Jede ausgewählte Seite erhält dabei genau eines der folgenden Rechte:

| Seitenrecht | Rechte |
|---|---|
| `Editor` | Seite anzeigen, bearbeiten, als Vorschau öffnen und zur Prüfung einreichen |
| `Publisher` | alle Editor-Rechte sowie die Seite veröffentlichen und zurückziehen |

Ist eine solche Einschränkung aktiv, zeigt die Seitenverwaltung ausschließlich die ausdrücklich zugewiesenen Seiten. Anlegen, Duplizieren, Löschen und Umsortieren bleiben gesperrt, weil diese Aktionen die gemeinsame Seitenstruktur oder nicht freigegebene Seiten beeinflussen können. Ohne Einschränkung gilt wie bisher die Rolle des Moduls `pages` für alle Seiten.

### 3.2 Alle anderen Module

| Rolle | Rechte |
|---|---|
| `Viewer` | Daten des Moduls lesen |
| `Editor` | lesen und die fachlichen Aktionen des Moduls ausführen |

Beispiele für Editor-Aktionen sind das Moderieren eines Gästebucheintrags, das Ändern eines Kontaktstatus, das Erfassen von Helferzeiten oder das erneute Bereitstellen eines fehlgeschlagenen Mitgliedsantrags.

Das Seitenmodul darf Aktivitäten lesend abrufen, damit der Seiteneditor vorhandene Aktivitäten in Veranstaltungsblöcken auswählen kann. Aktivitäten zu erstellen oder zu bearbeiten erfordert weiterhin die Rolle `Editor` im Modul `activities`.

## 4. Globale Administratorrollen

| Rolle | Wirkung |
|---|---|
| keine | Es gelten exakt die hinterlegten Modulrollen. |
| `Admin` | Innerhalb jedes freigeschalteten Moduls gelten alle fachlichen Rechte. Ein Admin kann keine SuperAdmins bearbeiten oder sperren und die Rolle SuperAdmin nicht vergeben. |
| `SuperAdmin` | Innerhalb jedes freigeschalteten Moduls gelten alle fachlichen Rechte. Ein SuperAdmin darf auch SuperAdmins und globale Rollen verwalten. |

Globale Rollen heben die Modulgrenze nicht auf. Ein Admin mit ausschließlich `pages` darf beispielsweise alle Seitenaktionen ausführen, sieht aber weder Mitgliedsanträge noch die Benutzerverwaltung.

Innerhalb des freigeschalteten Moduls `pages` ignorieren Admin und SuperAdmin eine eventuell gespeicherte Einschränkung auf einzelne Seiten und sehen sowie bearbeiten immer alle Seiten.

## 5. Benutzerverwaltung und Schutzregeln

Die Benutzerverwaltung selbst ist ebenfalls ein Modul:

- `Viewer` darf Benutzer und deren Zuweisungen ansehen.
- `Editor` darf reguläre Benutzer anlegen, bearbeiten und sperren.
- Ein globaler Admin mit diesem Modul darf reguläre Benutzer und Admins verwalten.
- Nur ein SuperAdmin mit diesem Modul darf SuperAdmins anlegen, bearbeiten oder sperren.

Folgende Regeln gelten immer serverseitig:

1. Ein Admin darf einen SuperAdmin weder bearbeiten noch sperren.
2. Ein Benutzer ohne globale Administratorrolle darf keinen Admin oder SuperAdmin bearbeiten oder sperren.
3. Nur ein SuperAdmin darf die Rolle `SuperAdmin` vergeben.
4. Nur Admin oder SuperAdmin dürfen die Rolle `Admin` vergeben.
5. Dem letzten aktiven SuperAdmin darf die Rolle nicht entzogen werden; er darf auch nicht gesperrt werden.
6. Jeder Benutzer besitzt höchstens eine globale Administratorrolle und mindestens eine Modulzuordnung.

Ein hartes Löschen von Benutzern wird nicht angeboten. Benutzer werden gesperrt, damit historische und künftige Audit-Bezüge erhalten bleiben.

## 6. Typische Profile

### Seitenredaktion

- globale Rolle: keine
- `pages: editor`
- darf Seiten bearbeiten, aber nicht veröffentlichen

### Veröffentlichung

- globale Rolle: keine
- `pages: publisher`
- darf Seiten bearbeiten, veröffentlichen und zurückziehen

### Gästebuch nur lesen

- globale Rolle: keine
- `guestbook: viewer`
- sieht Einträge, aber keine Moderationsaktionen

### Helfer- und Aktivitätenverwaltung

- globale Rolle: keine
- `activities: editor`
- `event_helpers: editor`

### Fachlicher Administrator für Seiten und Benutzer

- globale Rolle: `Admin`
- `pages: viewer`
- `user_management: viewer`
- die gespeicherte Viewer-Stufe wird in diesen beiden freigeschalteten Modulen durch Admin auf Vollzugriff angehoben
- andere Module bleiben unzugänglich

## 7. API- und Sitzungsdarstellung

Benutzer- und Sitzungsantworten liefern die globalen Rollen und die Modulzuordnungen getrennt:

```json
{
  "roles": ["admin"],
  "moduleAccess": {
    "pages": "viewer",
    "guestbook": "editor"
  },
  "pageAccess": {
    "page-id-1": "editor",
    "page-id-2": "publisher"
  }
}
```

`pageAccess: null` bedeutet uneingeschränkten Seitenzugriff gemäß Modulrolle. Die gespeicherte Modulrolle und der Seitenscope bleiben in der Antwort sichtbar, auch wenn eine globale Administratorrolle innerhalb des Moduls wirksam alle Rechte verleiht.

## 8. Sicherheits- und Architekturregeln

- Autorisierung wird am Admin-API-Endpunkt erzwungen; ausgeblendete Buttons sind keine Sicherheitsgrenze.
- Controller prüfen den groben Zugriff, fachliche Schutzregeln liegen in den UseCases der Logic Layer.
- Rollen- und Modulwerte werden ausschließlich über geschlossene Enums angenommen.
- Berechtigungsänderungen sind auditrelevante Vorgänge.
- Passwörter, Geheimnisse und personenbezogene Inhalte dürfen nicht in Logs oder Fehlerdetails erscheinen.

Dieses Dokument konkretisiert [architektur.md](./architektur.md), [architektur-patterns.md](./architektur-patterns.md) und [projekt-umsetzung.md](./projekt-umsetzung.md). Bei Widersprüchen gilt die dort definierte Priorität.
