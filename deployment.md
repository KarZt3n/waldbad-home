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

## Prod auf dem Telekom-Webspace

Prod läuft auf einem Telekom-Webspace (Shared Hosting, PHP 8.4, MariaDB, kein Docker, kein Cron),
erreichbar per SSH als `admin@w26zxq3b2.hier-im-netz.de` auf `hosting.telekom.de`.

Jeder neu gepushte Git-Tag startet `.github/workflows/deploy-prod.yaml`. Der Workflow läuft auf einem
GitHub-hosted Runner, weil der Webspace öffentlich erreichbar ist:

1. Er installiert die Abhängigkeiten ohne Dev-Pakete, kompiliert die Assets und packt das Release.
2. Er lädt das Paket nach `~/waldbad-home/incoming` hoch und führt dort `deploy/prod-deploy-remote.sh` aus.
3. Das Skript entpackt nach `~/waldbad-home/releases/<zeitstempel>-<tag>-<commit>`, wärmt den Cache auf,
   sichert die Datenbank nach `~/waldbad-home/backups/database` (die zehn neuesten bleiben), führt die
   Migrationen aus und schaltet erst danach um. Es bleiben die drei neuesten Releases erhalten.

Der Document-Root ist fest `~/public_html` und lässt sich nicht auf `public/` eines Releases legen. Das
Skript kopiert deshalb die öffentlichen Dateien dorthin und schreibt `public_html/index.php` als Stub, der
das aktive Release lädt; das Ersetzen dieses Stubs ist der eigentliche Umschaltpunkt. Hochgeladene Medien
liegen dauerhaft in `public_html/uploads/media`. Andere Dateien in `public_html` außer `uploads/` und
`.well-known/` werden beim Deployment entfernt.

Der Telekom-Webserver liest `.htaccess` erst, wenn `~/etc/httpd.conf` `AllowOverride All` für
`/home/www/public_html` freigibt ([Telekom-Hilfe](https://homepagecenter.telekom.de/hilfe/htaccess-aktivieren)).
Das Skript legt die Datei an, falls sie fehlt, und lädt die Konfiguration mit `apachectl reload` neu. Ohne
diesen Reload wirkt eine geänderte `httpd.conf` erst nach unbestimmter Zeit; solange liefern alle Unterseiten
außer der Startseite 404.

Mails werden in Prod synchron im Request versendet (`MESSENGER_TRANSPORT_DSN=sync://`), weil auf dem
Webspace kein dauerhafter Worker und kein Cron läuft.

### Einmalige Einrichtung

1. Im Telekom-Homepagecenter PHP 8.4 für den Webspace einstellen.
2. Auf dem Webspace `~/waldbad-home/shared/.env.local` anlegen (Rechte `0600`). Zugangsdaten werden nur
   dort oder verschlüsselt im Secrets-Vault (Schritt 3) gepflegt, nie im Klartext im Repository:

   ```dotenv
   APP_ENV=prod
   APP_DEBUG=0
   APP_SECRET=<openssl rand -hex 32>
   DATABASE_URL='mysql://<benutzer>:<passwort-url-kodiert>@<host>:3306/<datenbank>?serverVersion=<version>-MariaDB&charset=utf8mb4'
   DEFAULT_URI=https://<prod-domain>
   MAILER_DSN=null://null
   MAILER_FROM_ADDRESS=it@waldbad-borkheide.de
   MAILER_FROM_NAME="Naturbad Borkheide e.V."
   MESSENGER_TRANSPORT_DSN=sync://
   ```

   `MAILER_DSN` wird in Prod nicht verwendet (siehe Schritt 3), muss aber gesetzt sein.

3. Mails gehen in Prod über Bird (`eu1.smtp.bird.com:465`, siehe `config/packages/mailer.yaml`). Der
   API-Key (Scope `emails`) ist das SMTP-Passwort und liegt im Symfony-Secrets-Vault:

   ```bash
   php bin/console secrets:set BIRD_API_KEY --env=prod
   ```

   Die verschlüsselten Dateien unter `config/secrets/prod/` werden committet. Der Entschlüsselungsschlüssel
   `config/secrets/prod/prod.decrypt.private.php` bleibt außerhalb des Repositorys und wird einmalig nach
   `~/waldbad-home/shared/prod.decrypt.private.php` kopiert (Rechte `0600`); das Deployment verlinkt ihn in
   jedes Release und bricht ab, wenn er fehlt. Alternativ kann er als `SYMFONY_DECRYPTION_SECRET` in
   `.env.local` stehen. Damit Bird `it@waldbad-borkheide.de` als Absender annimmt und DMARC besteht, muss
   die Domain bei Bird verifiziert sein (DKIM-Einträge im Telekom-DNS).

4. Einen eigenen Deploy-Schlüssel erzeugen, den öffentlichen Teil in `~/.ssh/authorized_keys` des
   Webspace eintragen und in GitHub unter `Settings` → `Environments` die Umgebung `production` anlegen mit:
   - Secret `PROD_SSH_PRIVATE_KEY`: privater Deploy-Schlüssel
   - Secret `PROD_SSH_KNOWN_HOSTS`: Ausgabe von `ssh-keyscan -t ed25519 hosting.telekom.de`
   - optional Variable `PROD_BASE_URL` (z. B. `https://waldbad-borkheide.de`) für den Healthcheck

   Über `Required reviewers` an der Umgebung kann jedes Prod-Deployment zusätzlich freigegeben werden.

### Deployen

```bash
git tag v1.0.0
git push origin v1.0.0
```

Die Startseiten werden auf Prod nicht automatisch angelegt. Bei einer leeren Instanz einmalig:

```bash
ssh 'admin@w26zxq3b2.hier-im-netz.de@hosting.telekom.de' 'cd ~/waldbad-home/current && php8.4 bin/console app:site:initialize --no-interaction'
```

Ein Rollback erfolgt, indem der Workflow für einen älteren Tag erneut ausgeführt wird (`Actions` →
`Prod deploy` → `Run workflow` mit dem Tag). Die Datenbank wird dabei nicht zurückgesetzt; dafür liegen
die Sicherungen unter `~/waldbad-home/backups/database`.

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
