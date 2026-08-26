# Deployment von Waldbad Home

## Zielbild

Die Stage-Anwendung läuft auf der Proxmox-VM `web-apps-stage` als eigener Docker-Compose-Stack `waldbad-home`.

- öffentliche Adresse im lokalen Netz: `http://web-apps-stage.fritz.box:8083`
- Anwendung: Apache mit PHP 8.4 und Symfony
- Datenbank: MariaDB 11.4
- asynchrone Verarbeitung: separater Symfony-Messenger-Worker
- persistente Volumes: Datenbank und hochgeladene Medien
- Secrets: ausschließlich auf der VM unter `/srv/webapps/waldbad-home/secrets`

Die Entwicklungsdateien `compose.yaml` und `compose.override.yaml` bleiben davon unabhängig.

## Manuelles Stage-Deployment

Im ausgecheckten Repository auf der VM:

```bash
./deploy/stage-deploy.sh
```

Das Skript erzeugt beim ersten Lauf die benötigten Laufzeit-Secrets, baut das Image, startet beziehungsweise aktualisiert den Stack, führt Datenbankmigrationen aus und prüft anschließend die öffentliche API.

Status und Logs:

```bash
docker compose -f deploy/compose.stage.yaml ps
docker compose -f deploy/compose.stage.yaml logs -f --tail=100 app worker database
```

Symfony-Konsolenbefehle werden als kurzlebiger Container gestartet, damit die Docker Secrets korrekt geladen werden:

```bash
docker compose -f deploy/compose.stage.yaml run --rm --no-deps -e RUN_MIGRATIONS=0 app php bin/console <befehl>
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

Ein Rollback erfolgt über den gewünschten Git-Stand und dasselbe Deployment-Skript:

```bash
git checkout <commit-oder-tag>
./deploy/stage-deploy.sh
```

Datenbankmigrationen müssen vor einem Rollback auf Abwärtskompatibilität geprüft werden. Ein Code-Rollback setzt die Datenbank nicht automatisch zurück.

## Backups

Vor einem Produktivbetrieb müssen automatisierte, extern gespeicherte und regelmäßig wiederhergestellte Backups für beide Docker-Volumes eingerichtet werden. Ein Proxmox-VM-Backup allein ersetzt keine anwendungskonsistente Datenbanksicherung.
