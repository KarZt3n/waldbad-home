# Deployment von Waldbad Home

## Zielbild

Die Stage-Anwendung läuft auf der Proxmox-VM `web-apps-stage` als eigener Docker-Compose-Stack `waldbad-home`.

- interne Adresse im lokalen Netz: `http://web-apps-stage.fritz.box:8083`
- private HTTPS-Adresse über Tailscale: `https://waldbad-home-stage.tail183645.ts.net`
- Anwendung: Apache mit PHP 8.4 und Symfony
- Datenbank: MariaDB 11.4
- asynchrone Verarbeitung: separater Symfony-Messenger-Worker
- Releases: `/srv/webapps/waldbad-home/releases/<zeitstempel>-<commit>-<kennung>`
- aktives Release: Symlink `/srv/webapps/waldbad-home/current`
- persistentes Datenbank-Volume und releaseunabhängige Medien unter `/srv/webapps/waldbad-home/shared/media`
- Secrets: ausschließlich auf der VM unter `/srv/webapps/waldbad-home/secrets`

Die Entwicklungsdateien `compose.yaml` und `compose.override.yaml` bleiben davon unabhängig.

## Privater externer Zugriff

Tailscale läuft direkt auf der Stage-VM. `Tailscale Serve` stellt ausschließlich
innerhalb des Tailnets die HTTPS-Adresse
`https://waldbad-home-stage.tail183645.ts.net` bereit und leitet Anfragen intern
an `http://127.0.0.1:8083` weiter. An der FritzBox ist dafür keine Portfreigabe
eingerichtet. Docker veröffentlicht Port 8083 nur auf `127.0.0.1` und der
LAN-Adresse `192.168.178.119`; über die Tailscale-IP ist der unverschlüsselte
Port nicht direkt erreichbar.

Status prüfen:

```bash
tailscale status
tailscale serve status
```

Die Freigabe lässt sich bei Bedarf deaktivieren:

```bash
tailscale serve --https=443 off
```

### Browserzugang ohne Tailscale-Installation

Für ausgewählte Stage-Benutzer läuft ein vorgeschaltetes Nginx-Gateway mit
individueller HTTP-Basic-Authentifizierung. Das Gateway ist ausschließlich auf
`127.0.0.1:8084` gebunden und wird über Tailscale Funnel veröffentlicht. Das
Backend auf Port 8083 wird nicht direkt öffentlich freigegeben.

Benutzer verwalten:

```bash
./deploy/stage-access-user.sh add <benutzername>
./deploy/stage-access-user.sh list
./deploy/stage-access-user.sh remove <benutzername>
```

Passwörter werden ausschließlich als bcrypt-Hash auf dem Server unter
`/srv/webapps/waldbad-home/secrets/stage_htpasswd` gespeichert. Jeder Benutzer
erhält einen eigenen Zugang und kann unabhängig widerrufen werden.

## Neues Release auf Stage deployen

Jedes Deployment wird aus einem sauberen Git-Checkout gestartet. Nicht eingecheckte
Änderungen werden nicht deployt. Für ein manuelles Deployment auf der Stage-VM:

```bash
cd /pfad/zum/waldbad-home-checkout
git switch main
git pull --ff-only
./deploy/stage-deploy.sh
```

Das Skript führt den Release-Wechsel vollständig aus:

1. Es exportiert den aktuellen Git-Commit in ein neues Verzeichnis unter
   `/srv/webapps/waldbad-home/releases`.
2. Es erzeugt beim ersten Lauf die benötigten Laufzeit-Secrets, baut das Image,
   startet beziehungsweise aktualisiert den Stack und führt Datenbankmigrationen aus.
3. Es prüft die öffentliche API und synchronisiert die Medienmetadaten.
4. Erst nach erfolgreichem Abschluss setzt es den Symlink
   `/srv/webapps/waldbad-home/current` atomar auf das neue Release.
5. Es entfernt ältere Release-Verzeichnisse, sodass einschließlich des aktiven
   Releases nur die drei neuesten Releases erhalten bleiben. Datenbank, Medien und
   Secrets liegen außerhalb der Releases und werden dabei nicht gelöscht.

Schlägt ein Schritt fehl, wird `current` nicht auf das neue Release umgeschaltet und
das Skript endet mit einem Fehlercode.

Die redaktionellen Startseiten werden bei normalen Deployments bewusst nicht erneut
angelegt. Nur bei der erstmaligen Einrichtung einer vollständig leeren Instanz wird
die Initialisierung ausdrücklich aktiviert:

```bash
WALDBAD_INITIALIZE_SITE=1 ./deploy/stage-deploy.sh
```

Spätere Deployments laufen ohne diese Variable. Dadurch werden im CMS verschobene
oder bewusst gelöschte Standardseiten nicht erneut als Hauptseiten angelegt.

Hochgeladene Medien liegen nicht in einem Release. Stage bindet den gemeinsamen
Ordner `/srv/webapps/waldbad-home/shared/media` in den öffentlichen Anwendungspfad
`public/uploads/media` ein. Beim ersten Deployment nach der Umstellung übernimmt
das Skript automatisch den Inhalt des bisherigen Docker-Volumes. DDEV verwendet
weiterhin direkt `public/uploads/media` im lokalen Projekt. Für eine weitere
Produktionsinstanz kann der Hostpfad beim Deployment explizit gesetzt werden:

```bash
WALDBAD_SHARED_MEDIA_DIRECTORY=/srv/webapps/waldbad-home-prod/shared/media ./deploy/stage-deploy.sh
```

Status und Logs:

```bash
docker compose -f /srv/webapps/waldbad-home/current/deploy/compose.stage.yaml ps
docker compose -f /srv/webapps/waldbad-home/current/deploy/compose.stage.yaml logs -f --tail=100 app worker database
```

Symfony-Konsolenbefehle werden als kurzlebiger Container gestartet, damit die Docker Secrets korrekt geladen werden:

```bash
docker compose -f /srv/webapps/waldbad-home/current/deploy/compose.stage.yaml run --rm --no-deps -e RUN_MIGRATIONS=0 app php bin/console <befehl>
```

## GitHub Actions

Die VM liegt in einem privaten Netz und kann von einem GitHub-hosted Runner nicht direkt erreicht werden. Deshalb verwendet `.github/workflows/deploy-stage.yaml` einen Self-hosted Runner auf der Stage-VM mit den Labels:

```text
self-hosted, linux, x64, waldbad-stage
```

Der Workflow läuft ausschließlich bei Pushes auf `main` oder nach manueller Auslösung. Pull-Request-Code wird aus Sicherheitsgründen nie auf diesem Runner ausgeführt.

Runner in GitHub anlegen:

1. Repository `KarZt3n/waldbad-home` öffnen.
2. `Settings` → `Actions` → `Runners` → `New self-hosted runner` auswählen.
3. Linux und x64 wählen.
4. Die von GitHub angezeigten Download- und Konfigurationsbefehle als Benutzer `deploy` auf `web-apps-stage` ausführen.
5. Beim Konfigurieren zusätzlich das Label `waldbad-stage` vergeben.
6. Den Runner anschließend mit dem von GitHub ausgegebenen Service-Befehl als Systemdienst installieren und starten.

Der kurzlebige Registrierungstoken darf nicht in das Repository, in ein Skript oder in diese Dokumentation übernommen werden.

## Rollback

Ein Rollback erfolgt über den gewünschten Git-Stand und dasselbe Deployment-Skript.
Dadurch wird auch für den zurückgerollten Stand ein neues, nachvollziehbares Release
erzeugt und `current` erst nach erfolgreicher Prüfung umgeschaltet:

```bash
git checkout <commit-oder-tag>
./deploy/stage-deploy.sh
```

Datenbankmigrationen müssen vor einem Rollback auf Abwärtskompatibilität geprüft werden. Ein Code-Rollback setzt die Datenbank nicht automatisch zurück.

## Backups

Vor einem Produktivbetrieb müssen automatisierte, extern gespeicherte und regelmäßig wiederhergestellte Backups für beide Docker-Volumes eingerichtet werden. Ein Proxmox-VM-Backup allein ersetzt keine anwendungskonsistente Datenbanksicherung.
## Datenbank sichern und in Stage importieren

Die lokale DDEV-Datenbank wird als komprimiertes SQL-Backup exportiert:

```bash
./deploy/export-ddev-db.sh
```

Optional kann ein expliziter Zielpfad übergeben werden. Backups unter `var/backups/`
sind lokale Betriebsartefakte und dürfen nicht in Git eingecheckt werden.

Für einen Stage-Import wird das Backup zunächst auf den Webserver übertragen und
dort aus dem Deployment-Checkout ausgeführt:

```bash
./deploy/import-stage-db.sh /pfad/zum/backup.sql.gz --confirm-stage
```

Das Importskript erstellt vor jeder Änderung automatisch ein Backup der bisherigen
Stage-Datenbank unter `/srv/webapps/waldbad-home/backups/database`, stoppt App und
Worker während des Imports und prüft anschließend den Health-Endpunkt. Scheitert der
Import oder der anschließende Start, wird die vorherige Stage-Datenbank automatisch
wiederhergestellt. Der Import ersetzt ausschließlich die Datenbank. Medien aus
`public/uploads/media` müssen bei Bedarf separat synchronisiert werden.
